<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesResident;
use App\Http\Controllers\Controller;
use App\Models\Matter;
use App\Models\MatterQuestion;
use App\Models\Resident;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * 「大家都在问」：针对事项的公开问答。
 * 只有业主提问和负责方回答两种内容，业主间不互相回复；
 * 同问聚合热度，好答案可由团长沉淀成「买前必懂」词条。
 */
class MatterQuestionController extends Controller
{
    use ResolvesResident;

    /** 开放问答的事项类型：有决策疑问场景的（团购/活动）。 */
    private const QUESTION_TYPES = ['groupbuy', 'activity'];

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

        return response()->json([
            'data' => $questions->map(fn (MatterQuestion $question): array => [
                'id' => $question->id,
                'content' => $question->content,
                'asker' => $question->asker->displayName(),
                'is_mine' => $question->resident_id === $resident->id,
                'echo_count' => (int) ($question->echoers_count ?? 0),
                'echoed_by_me' => (bool) ($question->echoed_by_me ?? false),
                'answer' => $question->answer,
                'answered_by' => $question->answered_by,
                'answered_on' => $question->answered_at?->format('m-d'),
            ]),
            // 权限随人走：负责方看到回答入口，团长/管理员看到沉淀入口
            'can_ask' => $resident->affiliatedParty === null && in_array($matter->type, self::QUESTION_TYPES, true) && $matter->is_approved,
            'can_answer' => $this->canAnswer($matter, $resident),
            'can_promote' => $matter->type === 'groupbuy'
                && ($resident->is_admin || $matter->initiator_id === $resident->id),
        ]);
    }

    public function store(Request $request, Matter $matter): JsonResponse
    {
        $resident = $this->resident($request);

        abort_unless($matter->is_approved, 404);
        abort_unless(in_array($matter->type, self::QUESTION_TYPES, true), 422, '该事项不开放提问');
        abort_if($resident->affiliatedParty !== null, 403, '相关方身份不提问，如有疑问请切回业主身份');

        // 提问与接龙同一公示口径：楼栋 + 昵称
        if ($resident->unit_label === '') {
            throw ValidationException::withMessages([
                'profile' => '提问前请先在「我的 · 个人资料」里选好楼栋号',
            ]);
        }

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:300'],
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
        abort_if($question->resident_id === $resident->id, 422, '这是你自己提的问题');

        $changes = $question->echoers()->toggle($resident->id);
        $echoed = $changes['attached'] !== [];

        return response()->json(['data' => [
            'echoed_by_me' => $echoed,
            'echo_count' => $question->echoers()->count(),
        ]]);
    }

    /** 负责方回答（可修改）：署名快照跟着回答者身份走。 */
    public function answer(Request $request, MatterQuestion $question): JsonResponse
    {
        $resident = $this->resident($request);
        $matter = $question->matter;

        abort_unless((bool) $matter?->is_approved, 404);
        abort_unless($this->canAnswer($matter, $resident), 403, '只有团长、商家或管理员可以回答');

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:1000'],
        ]);

        $question->update([
            'answer' => $validated['content'],
            'answered_by' => $this->answererLabel($matter, $resident),
            'answered_at' => $question->answered_at ?? now(),
        ]);

        return response()->json(['data' => ['id' => $question->id]]);
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

    /** 谁能回答：团长本人、事项署名相关方（商家直供团的商家）的成员、管理员。 */
    private function canAnswer(Matter $matter, Resident $resident): bool
    {
        return $resident->is_admin
            || $matter->initiator_id === $resident->id
            || ($matter->initiator_party_id !== null && $resident->affiliated_party_id === $matter->initiator_party_id);
    }

    private function answererLabel(Matter $matter, Resident $resident): string
    {
        if ($matter->initiator_party_id !== null && $resident->affiliated_party_id === $matter->initiator_party_id) {
            return (string) $resident->affiliatedParty?->name;
        }

        if ($matter->initiator_id === $resident->id) {
            return $resident->displayName();
        }

        return '管理员';
    }
}
