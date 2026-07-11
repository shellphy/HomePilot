<?php

namespace App\Http\Controllers\Api;

use App\Events\MatterStateChanged;
use App\Http\Controllers\Api\Concerns\ResolvesResident;
use App\Http\Controllers\Controller;
use App\Http\Resources\MatterResource;
use App\Matters\MatterTypeRegistry;
use App\Models\Matter;
use App\Models\Stance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

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
                'joins' => fn ($query) => $query->with('resident')->oldest(),
                'updates' => fn ($query) => $query->latest('happened_on'),
                'reviews' => fn ($query) => $query->with('resident')->latest(),
            ]);

        $myReview = $matter->reviews->firstWhere('resident_id', $resident->id);

        return MatterResource::make($matter)
            ->additional([
                'joined' => $matter->joins->contains('resident_id', $resident->id),
                'my_review' => $myReview ? [
                    'rating' => (int) ($myReview->payload['rating'] ?? 0),
                    'content' => $myReview->payload['content'] ?? '',
                ] : null,
            ]);
    }

    /**
     * 发起事项：任何业主可发起（类型需允许），发起人即牵头人；管理员审核后公示。
     */
    public function store(Request $request): JsonResponse
    {
        $resident = $this->resident($request);

        $typeKey = $request->validate([
            'type' => ['required', Rule::in(MatterTypeRegistry::keys())],
        ])['type'];

        $type = MatterTypeRegistry::for($typeKey);
        abort_unless($type->userInitiatable(), 403, '该类型的事项由管理员发布');

        $validated = $request->validate($this->rulesFor($typeKey));

        $matter = Matter::create([
            'type' => $typeKey,
            'initiator_id' => $resident->id,
            'title' => $validated['title'],
            'category' => $validated['category'] ?? '',
            // 初始状态由类型的状态机决定，不接受客户端指定
            'state' => $type->initialState(),
            'is_approved' => false,
            'target_count' => $validated['target_count'] ?? 0,
            'payload' => $type->payloadFrom($validated),
        ]);

        return response()->json(['data' => MatterResource::make($matter)], 201);
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

        $previousState = $matter->state;

        $matter->update([
            'title' => $validated['title'],
            'category' => $validated['category'] ?? $matter->category,
            'state' => $validated['state'] ?? $matter->state,
            'target_count' => $validated['target_count'] ?? $matter->target_count,
            // 保留成交公示等不在编辑表单里的 payload 字段
            'payload' => array_merge($matter->payload ?? [], $type->payloadFrom($validated)),
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
