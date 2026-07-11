<?php

namespace App\Http\Controllers\Api;

use App\Events\MatterStateChanged;
use App\Http\Controllers\Api\Concerns\ResolvesResident;
use App\Http\Controllers\Controller;
use App\Http\Resources\MatterResource;
use App\Matters\MatterTypeRegistry;
use App\Models\Matter;
use App\Models\Party;
use App\Models\Resident;
use App\Models\Stance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MatterController extends Controller
{
    use ResolvesResident;

    /**
     * 小区事项流（仅已审核，混合类型）：按类型自己声明的权重排序。
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $resident = $this->resident($request);

        $matters = Matter::approved()
            ->with('initiatorParty')
            ->withCount('joins')
            ->withCount(['stances as register_count' => fn ($query) => $query->where('mode', Stance::MODE_REGISTER)])
            ->withExists(['stances as registered_by_me' => fn ($query) => $query
                ->where('mode', Stance::MODE_REGISTER)
                ->where('resident_id', $resident->id)])
            ->latest()
            ->get()
            ->filter(fn (Matter $matter): bool => $matter->typeDef()->visibleInList($matter))
            ->sortBy(fn (Matter $matter): int => $matter->typeDef()->sortWeight($matter))
            ->values();

        return MatterResource::collection($matters);
    }

    /**
     * 我参与过的事项（有接龙表态的）。
     */
    public function joined(Request $request): AnonymousResourceCollection
    {
        $resident = $this->resident($request);

        $matters = Matter::approved()
            ->whereHas('joins', fn ($query) => $query->whereBelongsTo($resident, 'resident'))
            ->withCount('joins')
            ->latest()
            ->get();

        return MatterResource::collection($matters);
    }

    /**
     * 我发起的事项（含待审核的）。
     */
    public function mine(Request $request): AnonymousResourceCollection
    {
        $matters = Matter::whereBelongsTo($this->resident($request), 'initiator')
            ->withCount('joins')
            ->latest()
            ->get();

        return MatterResource::collection($matters);
    }

    /**
     * 详情：未审核的事项只有发起人自己能看。
     */
    public function show(Request $request, Matter $matter): MatterResource
    {
        $resident = $this->resident($request);

        abort_unless($matter->is_approved || $matter->initiator_id === $resident->id, 404);

        $matter
            ->loadCount('joins')
            ->load([
                'initiator',
                'initiatorParty',
                'joins' => fn ($query) => $query->with('resident')->oldest(),
                'updates' => fn ($query) => $query->with('authorParty')->latest('happened_on'),
                'reviews' => fn ($query) => $query->with('resident')->latest(),
            ]);

        $myReview = $matter->reviews->firstWhere('resident_id', $resident->id);
        $myJoin = $matter->joins->firstWhere('resident_id', $resident->id);

        return MatterResource::make($matter)
            ->additional([
                'joined' => $myJoin !== null,
                'my_share_contact' => (bool) ($myJoin?->payload['share_contact'] ?? false),
                'my_review' => $myReview ? [
                    'rating' => (int) ($myReview->payload['rating'] ?? 0),
                    'content' => $myReview->payload['content'] ?? '',
                ] : null,
                ...$this->contactExchange($matter, $resident, $myJoin),
            ]);
    }

    /**
     * 联系方式互通（进入 contactsOpen 阶段后）：
     * 牵头人 ↔ 同意共享的参与者，双向、仅限双方，手机号不进入任何公示面。
     *
     * @return array{contacts: array<int, array{name: string, phone: string}>, initiator_contact: array{name: string, phone: string}|null}
     */
    private function contactExchange(Matter $matter, Resident $resident, ?Stance $myJoin): array
    {
        if (! $matter->typeDef()->contactsOpen($matter)) {
            return ['contacts' => [], 'initiator_contact' => null];
        }

        // 牵头人视角：同意共享且已授权手机号的参与者
        $contacts = $matter->initiator_id === $resident->id
            ? $matter->joins
                ->filter(fn (Stance $join): bool => (bool) ($join->payload['share_contact'] ?? false) && $join->resident->phone !== '')
                ->map(fn (Stance $join): array => ['name' => $join->resident->displayName(), 'phone' => $join->resident->phone])
                ->values()
                ->all()
            : [];

        // 参与者视角：自己同意了共享，才能看到牵头人的手机号（对等交换）
        $initiator = $matter->initiator;
        $initiatorContact = $myJoin !== null
            && (bool) ($myJoin->payload['share_contact'] ?? false)
            && $initiator !== null
            && $initiator->phone !== ''
            && $initiator->id !== $resident->id
                ? ['name' => $initiator->displayName(), 'phone' => $initiator->phone]
                : null;

        return ['contacts' => $contacts, 'initiator_contact' => $initiatorContact];
    }

    /**
     * 发起事项：业主可发起（类型需允许）；已认证商家可发起团购/活动（带商家署名）；
     * 其余相关方身份不发起（治理方走官方回应，公告/征集由管理端发布）。管理员审核后公示。
     */
    public function store(Request $request): JsonResponse
    {
        $resident = $this->resident($request);
        $party = $resident->affiliatedParty;

        $typeKey = $request->validate([
            'type' => ['required', Rule::in(MatterTypeRegistry::keys())],
        ])['type'];

        $type = MatterTypeRegistry::for($typeKey);
        abort_unless($type->userInitiatable(), 403, '该类型的事项由管理员发布');

        if ($party !== null) {
            abort_unless($party->type === Party::TYPE_MERCHANT, 403, '该身份不发起事项，如需张罗请切回业主身份');
            abort_unless($party->is_listed, 403, '商家发起需先由管理员认证，请联系管理员');
            abort_unless($type->merchantInitiatable(), 403, '商家可以发起团购和活动');
        }

        // 牵头人自己也要上「楼栋 + 昵称」的公示名单，和报名一样先选楼栋号
        if ($party === null && $resident->unit_label === '') {
            throw ValidationException::withMessages([
                'profile' => '发起前请先在「我的 · 个人资料」里选好楼栋号',
            ]);
        }

        $validated = $request->validate($this->rulesFor($typeKey));

        $matter = Matter::create([
            'type' => $typeKey,
            'initiator_id' => $resident->id,
            'initiator_party_id' => $party?->id,
            'title' => $validated['title'],
            'category' => $validated['category'] ?? '',
            // 初始状态由类型的状态机决定，不接受客户端指定
            'state' => $type->initialState(),
            'is_approved' => false,
            'target_count' => $validated['target_count'] ?? 0,
            'payload' => $type->payloadFrom($validated),
        ]);

        return response()->json(['data' => MatterResource::make($matter->load('initiatorParty'))], 201);
    }

    /**
     * 编辑：只有发起人本人；类型不可变。
     */
    public function update(Request $request, Matter $matter): JsonResponse
    {
        abort_unless($matter->initiator_id === $this->resident($request)->id, 403, '只有发起人可以操作');

        $type = $matter->typeDef();
        $validated = $request->validate(array_merge($this->rulesFor($matter->type), [
            'state' => ['sometimes', Rule::in(array_keys($type->states()))],
        ]));

        if (($validated['state'] ?? $matter->state) !== $matter->state) {
            $this->guardFinalState($matter);
        }

        $previousState = $matter->state;

        $matter->update([
            'title' => $validated['title'],
            'category' => $validated['category'] ?? $matter->category,
            'state' => $validated['state'] ?? $matter->state,
            'target_count' => $validated['target_count'] ?? $matter->target_count,
            // 保留成交公示等不在编辑表单里的 payload 字段；被驳回的事项编辑即重新提交（清掉驳回理由）
            'payload' => array_merge($matter->payload ?? [], $type->payloadFrom($validated), ['reject_reason' => '']),
        ]);

        if ($matter->state !== $previousState) {
            MatterStateChanged::dispatch($matter, $previousState);
        }

        return response()->json(['data' => MatterResource::make($matter)]);
    }

    /**
     * 单独流转状态（发起人操作），避免为改一个字段回传整份表单。
     */
    public function updateState(Request $request, Matter $matter): JsonResponse
    {
        abort_unless($matter->initiator_id === $this->resident($request)->id, 403, '只有发起人可以操作');

        $validated = $request->validate([
            'state' => ['required', Rule::in(array_keys($matter->typeDef()->states()))],
        ]);

        if ($validated['state'] !== $matter->state) {
            $this->guardFinalState($matter);
        }

        $previousState = $matter->state;
        $matter->update($validated);

        if ($matter->state !== $previousState) {
            MatterStateChanged::dispatch($matter, $previousState);
        }

        return response()->json(['data' => MatterResource::make($matter)]);
    }

    /**
     * 成交公示（团购专属，发起人操作，成团后）：最终条件 + 返点让利去向。
     */
    public function updateDeal(Request $request, Matter $matter): JsonResponse
    {
        abort_unless($matter->initiator_id === $this->resident($request)->id, 403, '只有发起人可以操作');
        abort_unless($matter->type === 'groupbuy', 404);
        abort_unless($matter->state === 'done', 422, '流转到「已成团」后才能发布成交公示');

        $validated = $request->validate([
            'final_terms' => ['required', 'array', 'min:1'],
            'final_terms.*.label' => ['required', 'string', 'max:30'],
            'final_terms.*.value' => ['required', 'string', 'max:100'],
            'final_note' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ], [
            'final_terms.required' => '至少公示一条成交条件',
        ]);

        $matter->update([
            'payload' => array_merge($matter->payload ?? [], [
                'final_terms' => $validated['final_terms'],
                'final_note' => $validated['final_note'] ?? '',
            ]),
        ]);

        return response()->json(['data' => MatterResource::make($matter)]);
    }

    /**
     * 终态锁：已进入终态（如已成团/已有结果）后，联系方式已互通、评价已开放，
     * 发起人不能再把状态改回去；确需纠错找管理员（管理端编辑不受此限）。
     */
    private function guardFinalState(Matter $matter): void
    {
        $type = $matter->typeDef();

        abort_if(
            $type->isFinalState($matter->state),
            422,
            '已进入「'.$type->stateLabel($matter->state).'」，状态不能再回退；如需纠错请联系管理员',
        );
    }

    /**
     * 组合该类型的完整校验规则：通用 + 列级 + payload。
     * 状态不在其中：创建时由状态机给初始值，编辑/流转各自单独校验。
     *
     * @return array<string, mixed>
     */
    private function rulesFor(string $typeKey): array
    {
        $type = MatterTypeRegistry::for($typeKey);

        return array_merge(
            ['title' => ['required', 'string', 'max:60']],
            $type->baseRules(),
            $type->payloadRules(),
        );
    }
}
