<?php

namespace App\Http\Controllers\Api;

use App\Enums\MatterReviewStatus;
use App\Events\MatterDealPosted;
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
            ->withCount(['joins', 'confirmedJoins'])
            ->withCount(['stances as register_count' => fn ($query) => $query->where('mode', Stance::MODE_REGISTER)])
            ->withExists(['stances as registered_by_me' => fn ($query) => $query
                ->where('mode', Stance::MODE_REGISTER)
                ->where('resident_id', $resident->id)])
            ->latest()
            ->get()
            ->filter(fn (Matter $matter): bool => MatterTypeRegistry::for($matter->type)->visibleInList($matter))
            ->sortBy(fn (Matter $matter): int => MatterTypeRegistry::for($matter->type)->sortWeight($matter))
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
            ->withCount(['joins', 'confirmedJoins'])
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
            ->withCount(['joins', 'confirmedJoins'])
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

        abort_unless($matter->is_approved || $matter->initiator_id === $resident->id || $resident->is_admin, 404);

        $matter
            ->loadCount(['joins', 'confirmedJoins'])
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
                // 我的承诺档位（团购分意向/确认两档）：接龙中的意向登记者据此看到「确认参团」入口
                'my_join_stage' => $myJoin?->joinStageValue(),
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
        $type = MatterTypeRegistry::for($matter->type);

        if (! $type->contactsOpen($matter)) {
            return ['contacts' => [], 'initiator_contact' => null];
        }

        // 牵头人视角：同意共享、档位够格（标品团只互通确认参团的）且已授权手机号的参与者
        $contacts = $matter->initiator_id === $resident->id
            ? $matter->joins
                ->filter(fn (Stance $join): bool => (bool) ($join->payload['share_contact'] ?? false)
                    && $type->contactEligible($matter, $join)
                    && $join->resident->phone !== '')
                ->map(fn (Stance $join): array => ['name' => $join->resident->displayName(), 'phone' => $join->resident->phone])
                ->values()
                ->all()
            : [];

        // 参与者视角：自己同意了共享且档位够格，才能看到牵头人的手机号（对等交换）
        $initiator = $matter->initiator;
        $initiatorContact = $myJoin !== null
            && (bool) ($myJoin->payload['share_contact'] ?? false)
            && $type->contactEligible($matter, $myJoin)
            && $initiator !== null
            && $initiator->phone !== ''
            && $initiator->id !== $resident->id
                ? ['name' => $initiator->displayName(), 'phone' => $initiator->phone]
                : null;

        return ['contacts' => $contacts, 'initiator_contact' => $initiatorContact];
    }

    /**
     * 发起事项：业主可发起（类型需允许）；已认证商家可发起团购/活动（带商家署名）；
     * 其余相关方身份不发起（治理方走官方回应）。管理员不受发起权/身份/楼栋护栏限制，
     * 可代发任何类型并显式署名（物业/业委会的调研）。两条路径都进待审，管理员审核后公示。
     */
    public function store(Request $request): JsonResponse
    {
        $resident = $this->resident($request);
        $isAdmin = $resident->is_admin;
        $party = $resident->affiliatedParty;

        $typeKey = $request->validate([
            'type' => ['required', Rule::in(MatterTypeRegistry::keys())],
        ])['type'];

        $type = MatterTypeRegistry::for($typeKey);

        if (! $isAdmin) {
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
        }

        $rules = $this->rulesFor($typeKey);

        if ($isAdmin) {
            // 署名发起：管理员代建的调研可亮明发起方
            $rules['initiator_party_id'] = ['sometimes', 'nullable', Rule::exists('parties', 'id')];
        }

        $validated = $request->validate($rules);

        $matter = Matter::create([
            'type' => $typeKey,
            'initiator_id' => $resident->id,
            'initiator_party_id' => $isAdmin
                ? ($validated['initiator_party_id'] ?? null)
                : $party?->id,
            'title' => $validated['title'],
            'category' => $validated['category'] ?? '',
            'state' => $type->initialState(),
            'target_count' => $validated['target_count'] ?? 0,
            'payload' => $type->payloadFrom($validated),
        ]);

        return response()->json(['data' => MatterResource::make($matter->load('initiatorParty'))], 201);
    }

    /**
     * 编辑：发起人本人或管理员；类型不可变。管理员为纠错通道——绕过顺序流转守卫
     * 与发起时锁定的键，并可改署名；发起人只能沿状态机推进，锁定键不可动。
     */
    public function update(Request $request, Matter $matter): JsonResponse
    {
        $resident = $this->resident($request);
        $isAdmin = $resident->is_admin;
        abort_unless($isAdmin || $matter->initiator_id === $resident->id, 403, '只有发起人可以操作');

        $type = MatterTypeRegistry::for($matter->type);
        $rules = array_merge($this->rulesFor($matter->type), [
            'state' => ['sometimes', Rule::in(array_keys($type->allStates()))],
        ]);

        if ($isAdmin) {
            $rules['initiator_party_id'] = ['sometimes', 'nullable', Rule::exists('parties', 'id')];
            $rules['is_approved'] = ['sometimes', 'boolean'];
        }

        $validated = $request->validate($rules);

        // 发起人受顺序流转守卫（跳步/回退/终态回退都拦），管理员绕过（Rule::in 仍拦非法值）
        if (! $isAdmin && ($validated['state'] ?? $matter->state) !== $matter->state) {
            $this->guardTransition($matter, $validated['state']);
        }

        $previousState = $matter->state;

        $payload = array_merge($matter->payload ?? [], $type->payloadFrom($validated));

        // 发起时锁定的键（如方案型开关）发起人编辑不可改，纠错走管理端
        if (! $isAdmin) {
            foreach ($type->lockedPayloadKeys() as $lockedKey) {
                $payload[$lockedKey] = $matter->payloadValue($lockedKey);
            }
        }

        $updateData = [
            'title' => $validated['title'],
            'category' => $validated['category'] ?? $matter->category,
            'state' => $validated['state'] ?? $matter->state,
            'target_count' => $validated['target_count'] ?? $matter->target_count,
            'payload' => $payload,
        ];

        if ($isAdmin) {
            $updateData['initiator_party_id'] = array_key_exists('initiator_party_id', $validated)
                ? $validated['initiator_party_id']
                : $matter->initiator_party_id;
        }

        // 被驳回的事项，发起人编辑保存即重新提交审核：打回待审、清掉驳回理由
        if ($matter->review_status === MatterReviewStatus::Rejected) {
            $updateData['review_status'] = MatterReviewStatus::Pending;
            $updateData['reject_reason'] = '';
        }

        // 管理端「公示到小区页」开关（发起人不下发此键）：勾上→通过并清理驳回理由；
        // 把已公示的撤下→回待审核；待审/驳回态只编辑其它字段时保持原状态
        if ($isAdmin && array_key_exists('is_approved', $validated)) {
            if ($validated['is_approved']) {
                $updateData['review_status'] = MatterReviewStatus::Approved;
                $updateData['reject_reason'] = '';
            } elseif ($matter->is_approved) {
                $updateData['review_status'] = MatterReviewStatus::Pending;
            }
        }

        $matter->update($updateData);

        if ($matter->state !== $previousState) {
            $matter->recordActivity($resident);
            MatterStateChanged::dispatch($matter, $previousState, $resident);
        }

        return response()->json(['data' => MatterResource::make($matter)]);
    }

    /**
     * 删除事项（管理员纠错通道，软删除，表态一并保留）。
     */
    public function destroy(Request $request, Matter $matter): JsonResponse
    {
        abort_unless($this->resident($request)->is_admin, 403);

        $matter->delete();

        return response()->json(['deleted' => true]);
    }

    /**
     * 单独流转状态（发起人操作），避免为改一个字段回传整份表单。
     */
    public function updateState(Request $request, Matter $matter): JsonResponse
    {
        $resident = $this->resident($request);
        abort_unless($matter->initiator_id === $resident->id, 403, '只有发起人可以操作');

        $validated = $request->validate([
            'state' => ['required', Rule::in(array_keys(MatterTypeRegistry::for($matter->type)->allStates()))],
        ]);

        if ($validated['state'] !== $matter->state) {
            $this->guardTransition($matter, $validated['state']);
        }

        $previousState = $matter->state;
        $matter->update($validated);

        if ($matter->state !== $previousState) {
            $matter->recordActivity($resident);
            MatterStateChanged::dispatch($matter, $previousState, $resident);
        }

        return response()->json(['data' => MatterResource::make($matter)]);
    }

    /**
     * 成交公示（团购专属，发起人操作，成团后）：最终条件 + 返点让利去向。
     */
    public function updateDeal(Request $request, Matter $matter): JsonResponse
    {
        $resident = $this->resident($request);
        abort_unless($matter->initiator_id === $resident->id, 403, '只有发起人可以操作');
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

        $matter->recordActivity($resident);
        MatterDealPosted::dispatch($matter);

        return response()->json(['data' => MatterResource::make($matter)]);
    }

    /**
     * 状态流转守卫（发起人侧）：终态（如已成团/已有结果/未成团）后联系方式已互通、评价已开放，
     * 不能再回退；非终态之间只能沿状态机顺序推进一步，或直接收场（旁路终态），跳步/回退都不行。
     * 确需纠错找管理员（管理端编辑不受此限）。
     */
    private function guardTransition(Matter $matter, string $to): void
    {
        $type = MatterTypeRegistry::for($matter->type);

        abort_if(
            $type->isFinalState($matter->state),
            422,
            '已进入「'.$type->stateLabel($matter->state).'」，状态不能再回退；如需纠错请联系管理员',
        );

        abort_unless(
            $type->canAdvanceTo($matter->state, $to),
            422,
            '状态只能按「'.implode(' → ', $type->states()).'」逐步推进'
                .($type->hasAbort() ? '，或直接收场为「'.$type->abortLabel().'」' : '')
                .'；如需纠错请联系管理员',
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
