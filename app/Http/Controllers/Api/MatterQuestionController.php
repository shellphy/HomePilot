<?php

namespace App\Http\Controllers\Api;

use App\Enums\SecCheckScene;
use App\Http\Controllers\Api\Concerns\ResolvesResident;
use App\Http\Controllers\Controller;
use App\Models\Matter;
use App\Models\MatterQuestion;
use App\Models\Resident;
use App\Rules\SafeText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * 「大家都在问」：针对事项的公开问答。填好资料的业主、已核验相关方都能问能答；
 * 同问聚合热度，好答案可由团长沉淀成「买前必懂」词条。本人或管理员可删问答，管理员可拉黑成员。
 */
class MatterQuestionController extends Controller
{
    use ResolvesResident;

    public function index(Request $request, Matter $matter): JsonResponse
    {
        $resident = $this->resident($request);

        abort_unless($matter->is_approved || $matter->initiator_id === $resident->id, 404);

        $questions = $matter->questions()
            ->with('asker')
            ->withCount('echoers')
            ->withExists(['echoers as echoed_by_me' => fn ($query) => $query->whereKey($resident->id)])
            ->orderByRaw('(answered_at is not null) desc')
            ->orderByDesc('echoers_count')
            ->latest('id')
            ->get();

        $canParticipate = $matter->is_approved && $this->canParticipate($resident);

        return response()->json([
            'data' => $questions->map(fn (MatterQuestion $question): array => [
                'id' => $question->id,
                'content' => $question->content,
                'asker' => $question->asker->displayName(),
                // 拉黑发问人要定位到人，仅管理员可见
                'asker_id' => $resident->is_admin ? $question->resident_id : null,
                'is_mine' => $question->resident_id === $resident->id,
                'answer_is_mine' => $question->answered_by_id === $resident->id,
                'echo_count' => (int) ($question->echoers_count ?? 0),
                'echoed_by_me' => (bool) ($question->echoed_by_me ?? false),
                'answer' => $question->answer,
                'answered_by' => $question->answered_by,
                // 拉黑回复人要定位到人，仅管理员可见
                'answerer_id' => $resident->is_admin ? $question->answered_by_id : null,
                'answered_on' => $question->answered_at?->format('m-d'),
            ]),
            'can_ask' => $canParticipate,
            'can_answer' => $canParticipate,
            'can_promote' => $matter->type === 'groupbuy'
                && ($resident->is_admin || $matter->initiator_id === $resident->id),
            // 管理员：删内容、拉黑成员的入口
            'can_moderate' => $resident->is_admin,
        ]);
    }

    public function store(Request $request, Matter $matter): JsonResponse
    {
        $resident = $this->resident($request);

        abort_unless($matter->is_approved, 404);
        $this->assertCanParticipate($resident, '提问');

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:300', new SafeText($resident, SecCheckScene::Forum)],
        ]);

        $question = $matter->questions()->create([
            'resident_id' => $resident->id,
            'content' => $validated['content'],
        ]);

        return response()->json(['data' => ['id' => $question->id]], 201);
    }

    /** 同问（toggle）：自己的问题不用同问。 */
    public function echo(Request $request, MatterQuestion $question): JsonResponse
    {
        $resident = $this->resident($request);

        // 事项被软删后问题仍在库里，对外一律 404
        abort_unless((bool) $question->matter?->is_approved, 404);
        $this->assertNotBlocked($resident);
        abort_if($question->resident_id === $resident->id, 422, '这是你自己提的问题');

        $changes = $question->echoers()->toggle($resident->id);
        $echoed = $changes['attached'] !== [];

        return response()->json(['data' => [
            'echoed_by_me' => $echoed,
            'echo_count' => $question->echoers()->count(),
        ]]);
    }

    /** 回答（可修改）：署名快照跟着回答者身份走。 */
    public function answer(Request $request, MatterQuestion $question): JsonResponse
    {
        $resident = $this->resident($request);
        $matter = $question->matter;

        abort_unless((bool) $matter?->is_approved, 404);
        $this->assertCanParticipate($resident, '回复');

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:1000', new SafeText($resident, SecCheckScene::Forum)],
        ]);

        $question->update([
            'answer' => $validated['content'],
            'answered_by' => $this->answererLabel($resident),
            'answered_by_id' => $resident->id,
            'answered_at' => $question->answered_at ?? now(),
        ]);

        return response()->json(['data' => ['id' => $question->id]]);
    }

    /** 删除整条问答（管理员或提问本人；回答与同问记录随之清除）。 */
    public function destroy(Request $request, MatterQuestion $question): JsonResponse
    {
        $resident = $this->resident($request);
        abort_unless($resident->is_admin || $question->resident_id === $resident->id, 403);

        $question->delete();

        return response()->json(['deleted' => true]);
    }

    /** 只删回复保留问题（管理员或回复本人）。 */
    public function destroyAnswer(Request $request, MatterQuestion $question): JsonResponse
    {
        $resident = $this->resident($request);
        abort_unless($resident->is_admin || $question->answered_by_id === $resident->id, 403);

        $question->update([
            'answer' => null,
            'answered_by' => '',
            'answered_by_id' => null,
            'answered_at' => null,
        ]);

        return response()->json(['deleted' => true]);
    }

    /**
     * 沉淀为「买前必懂」：把答过的问题变成决策卡词条，后来的业主不用再问。
     */
    public function promote(Request $request, MatterQuestion $question): JsonResponse
    {
        $resident = $this->resident($request);
        $matter = $question->matter;

        abort_if($matter === null, 404);
        abort_unless($matter->type === 'groupbuy', 422, '只有团购有「买前必懂」');
        abort_unless($resident->is_admin || $matter->initiator_id === $resident->id, 403, '只有团长或管理员可以沉淀');
        abort_if($question->answer === null, 422, '先回答这个问题，再沉淀');

        $validated = $request->validate([
            'term' => ['required', 'string', 'max:30'],
        ]);

        $glossary = $matter->payloadList('glossary');
        abort_if(
            collect($glossary)->contains(fn (array $entry): bool => $entry['term'] === $validated['term']),
            422,
            '「买前必懂」里已经有这个词了',
        );

        $glossary[] = [
            'term' => $validated['term'],
            'explain' => Str::limit((string) $question->answer, 497),
        ];
        $matter->update(['payload' => array_merge($matter->payload ?? [], ['glossary' => $glossary])]);

        return response()->json(['data' => ['term' => $validated['term']]]);
    }

    /** 能否参与(问/答)：业主按钮照显示（提交时再校验楼栋），已核验相关方放行，被拉黑不显示。 */
    private function canParticipate(Resident $resident): bool
    {
        if ($resident->isBlocked()) {
            return false;
        }

        if ($resident->affiliatedParty !== null) {
            return $resident->affiliatedParty->is_listed;
        }

        return true;
    }

    /** 提交前置：拉黑挡下、相关方须已核验、业主须先填楼栋。 */
    private function assertCanParticipate(Resident $resident, string $verb): void
    {
        $this->assertNotBlocked($resident);

        if ($resident->affiliatedParty !== null) {
            abort_unless($resident->affiliatedParty->is_listed, 403, '相关方通过核验后才能'.$verb);

            return;
        }

        if ($resident->unit_label === '') {
            throw ValidationException::withMessages([
                'profile' => $verb.'前请先在「我的 · 个人资料」里选好楼栋号',
            ]);
        }
    }

    /** 回答署名：机构成员署机构名，管理员署「管理员」，其余署 楼栋 + 昵称。 */
    private function answererLabel(Resident $resident): string
    {
        if ($resident->affiliatedParty !== null) {
            return (string) $resident->affiliatedParty->name;
        }

        if ($resident->is_admin) {
            return '管理员';
        }

        return $resident->displayName();
    }
}
