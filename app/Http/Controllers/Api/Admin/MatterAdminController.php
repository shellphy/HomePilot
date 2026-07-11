<?php

namespace App\Http\Controllers\Api\Admin;

use App\Events\MatterApproved;
use App\Events\MatterStateChanged;
use App\Http\Controllers\Api\Concerns\ResolvesResident;
use App\Http\Controllers\Controller;
use App\Matters\MatterTypeRegistry;
use App\Models\Matter;
use App\Models\Stance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * 管理端 · 事项：审核、发布（公告/征集等管理员专属类型）、编辑、删除。
 */
class MatterAdminController extends Controller
{
    use ResolvesResident;

    /**
     * 全部事项（含待审核）；?pending=1 只看待审核队列。
     */
    public function index(Request $request): JsonResponse
    {
        $matters = Matter::query()
            ->when($request->boolean('pending'), fn ($query) => $query->where('is_approved', false))
            ->with(['initiator', 'initiatorParty'])
            ->withCount('joins')
            ->withCount(['stances as register_count' => fn ($query) => $query->where('mode', Stance::MODE_REGISTER)])
            ->latest()
            ->get();

        return response()->json([
            'data' => $matters->map(fn (Matter $matter): array => $this->present($matter)),
            'pending_count' => Matter::where('is_approved', false)->count(),
        ]);
    }

    public function show(Matter $matter): JsonResponse
    {
        $matter->loadCount('joins')
            ->loadCount(['stances as register_count' => fn ($query) => $query->where('mode', Stance::MODE_REGISTER)]);

        return response()->json(['data' => $this->present($matter)]);
    }

    /**
     * 管理员发布事项（任何类型，包括公告/征集这类业主不能自发的），默认直接公示。
     */
    public function store(Request $request): JsonResponse
    {
        $typeKey = $request->validate([
            'type' => ['required', Rule::in(MatterTypeRegistry::keys())],
        ])['type'];

        $validated = $request->validate($this->rules($typeKey));

        $matter = Matter::create([
            'type' => $typeKey,
            'initiator_id' => null,
            'title' => $validated['title'],
            'category' => $validated['category'] ?? '',
            'state' => $validated['state'] ?? MatterTypeRegistry::for($typeKey)->initialState(),
            'is_approved' => $validated['is_approved'] ?? true,
            'target_count' => $validated['target_count'] ?? 0,
            'payload' => $this->payloadFrom($validated, $typeKey),
        ]);

        return response()->json(['data' => $this->present($matter)], 201);
    }

    /**
     * 管理员编辑：可改任何字段；payload 按键合并，保留不在表单里的（如成交公示）。
     */
    public function update(Request $request, Matter $matter): JsonResponse
    {
        $validated = $request->validate($this->rules($matter->type));

        $previousState = $matter->state;

        $matter->update([
            'title' => $validated['title'],
            'category' => $validated['category'] ?? $matter->category,
            'state' => $validated['state'] ?? $matter->state,
            'is_approved' => $validated['is_approved'] ?? $matter->is_approved,
            'target_count' => $validated['target_count'] ?? $matter->target_count,
            'payload' => array_merge($matter->payload ?? [], $this->payloadFrom($validated, $matter->type)),
        ]);

        if ($matter->state !== $previousState) {
            $matter->recordActivity($this->resident($request));
            MatterStateChanged::dispatch($matter, $previousState);
        }

        return response()->json(['data' => $this->present($matter)]);
    }

    /**
     * 审核：通过（公示给全小区）或驳回/撤下（附理由，发起人在详情页看到，编辑后即重新提交）。
     */
    public function approve(Request $request, Matter $matter): JsonResponse
    {
        $validated = $request->validate([
            'is_approved' => ['required', 'boolean'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:200'],
        ]);

        $wasApproved = $matter->is_approved;
        $matter->update([
            'is_approved' => $validated['is_approved'],
            'payload' => array_merge($matter->payload ?? [], [
                'reject_reason' => $validated['is_approved'] ? '' : ($validated['reason'] ?? ''),
            ]),
        ]);

        // 审核结果对发起人是关键动态：喂给「我的」页未读红点。
        // 驳回时待审事项的 is_approved 本来就是 false，不能只看变化——每次驳回（理由可能更新）都记
        if ($matter->is_approved !== $wasApproved || ! $matter->is_approved) {
            $matter->recordActivity($this->resident($request));
        }

        if ($matter->is_approved && ! $wasApproved) {
            MatterApproved::dispatch($matter);
        }

        return response()->json(['data' => $this->present($matter)]);
    }

    public function destroy(Matter $matter): JsonResponse
    {
        $matter->delete();

        return response()->json(['deleted' => true]);
    }

    /**
     * 登记明细（含楼栋/房号/手机等仅管理员可见字段），答案换算成题面文字。
     */
    public function registrations(Matter $matter): JsonResponse
    {
        $questions = collect($matter->payloadList('modules'))
            ->flatMap(fn (array $module): array => $module['questions'] ?? [])
            ->keyBy('key');

        $registrations = $matter->stances()
            ->where('mode', Stance::MODE_REGISTER)
            ->with('resident')
            ->latest()
            ->get()
            ->map(function (Stance $stance) use ($questions): array {
                $answers = $stance->payload['answers'] ?? [];

                return [
                    'id' => $stance->id,
                    'unit_label' => $stance->resident->unit_label,
                    'room_label' => $stance->resident->room_label,
                    'nickname' => $stance->resident->nickname,
                    'phone' => $stance->resident->phone,
                    'created_at' => $stance->created_at?->format('Y-m-d H:i'),
                    'answers' => collect(is_array($answers) ? $answers : [])
                        ->map(fn (mixed $value, int|string $key): array => [
                            'question' => $questions[$key]['text'] ?? $key,
                            'answer' => is_array($value) ? implode('、', $value) : (string) $value,
                        ])
                        ->values(),
                ];
            });

        return response()->json(['data' => $registrations]);
    }

    /**
     * 审核队列里的发起人署名：相关方身份的事项亮明身份快照（审核商家发起的事项时一眼可辨）。
     */
    private function initiatorLabel(Matter $matter): string
    {
        if ($matter->initiatorParty) {
            return $matter->initiatorParty->typeLabel().' · '.$matter->initiatorParty->name
                .($matter->initiatorParty->is_listed ? '（已认证）' : '（未认证）');
        }

        return $matter->initiator?->displayName() ?: '管理员发布';
    }

    /**
     * 管理端一览用的完整视图：原始 payload（编辑表单要用）+ 审核状态 + 发起人。
     *
     * @return array<string, mixed>
     */
    private function present(Matter $matter): array
    {
        $type = $matter->typeDef();

        return [
            'id' => $matter->id,
            'type' => $matter->type,
            'type_label' => $type->label(),
            'title' => $matter->title,
            'category' => $matter->category,
            'state' => $matter->state,
            'state_label' => $type->states()[$matter->state] ?? $matter->state,
            'states' => $type->states(),
            'is_approved' => $matter->is_approved,
            'target_count' => $matter->target_count,
            'initiator' => $this->initiatorLabel($matter),
            'join_count' => (int) ($matter->joins_count ?? 0),
            'register_count' => (int) ($matter->register_count ?? 0),
            'payload' => $matter->payload ?? (object) [],
            'created_at' => $matter->created_at?->format('Y-m-d H:i'),
        ];
    }

    /**
     * 管理端校验：通用字段 + 按类型的 payload 结构（征集问卷校验最严，答案按它落库）。
     * 列级字段（category/target_count）与业主前台同一份 baseRules：
     * 管理员代发的团购同样必须有品类与目标人数，两条创建路径校验强度一致。
     *
     * @return array<string, mixed>
     */
    private function rules(string $typeKey): array
    {
        $type = MatterTypeRegistry::for($typeKey);

        $rules = [
            'title' => ['required', 'string', 'max:60'],
            'category' => ['sometimes', 'nullable', 'string', 'max:30'],
            'state' => ['sometimes', Rule::in(array_keys($type->states()))],
            'is_approved' => ['sometimes', 'boolean'],
            'target_count' => ['sometimes', 'integer', 'min:0'],
            ...$type->baseRules(),
            'payload' => ['sometimes', 'array'],
            'payload.pitch' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'payload.body' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'payload.perk' => ['sometimes', 'nullable', 'string', 'max:200'],
            'payload.terms' => ['sometimes', 'array'],
            'payload.terms.*.label' => ['required', 'string', 'max:30'],
            'payload.terms.*.value' => ['required', 'string', 'max:100'],
            'payload.glossary' => ['sometimes', 'array'],
            'payload.glossary.*.term' => ['required', 'string', 'max:30'],
            'payload.glossary.*.explain' => ['required', 'string', 'max:500'],
        ];

        if ($typeKey === 'census') {
            $rules += [
                'payload.collects_contact' => ['sometimes', 'boolean'],
                'payload.modules' => ['sometimes', 'array'],
                'payload.modules.*.key' => ['sometimes', 'string', 'max:30'],
                'payload.modules.*.title' => ['required', 'string', 'max:30'],
                'payload.modules.*.intro' => ['sometimes', 'nullable', 'string', 'max:200'],
                // 允许空模块：小程序端「先建模块再逐题添加」的中间态；业主端渲染时跳过
                'payload.modules.*.questions' => ['sometimes', 'array'],
                'payload.modules.*.questions.*.key' => ['sometimes', 'string', 'max:30'],
                'payload.modules.*.questions.*.text' => ['required', 'string', 'max:100'],
                'payload.modules.*.questions.*.type' => ['required', Rule::in(['single', 'multi', 'text'])],
                'payload.modules.*.questions.*.note' => ['sometimes', 'nullable', 'string', 'max:200'],
                'payload.modules.*.questions.*.required' => ['sometimes', 'boolean'],
                // 填空题没有选项（前端不传该键）；选择题至少两个
                'payload.modules.*.questions.*.options' => ['required_unless:payload.modules.*.questions.*.type,text', 'array', 'min:2'],
                'payload.modules.*.questions.*.options.*' => ['required', 'string', 'max:50'],
            ];
        }

        return $rules;
    }

    /**
     * 取出请求里的 payload，并给征集模块/题目自动补 key（答案按 key 存，缺失时生成、已有的不动）。
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function payloadFrom(array $validated, string $typeKey): array
    {
        $payload = $validated['payload'] ?? [];

        if ($typeKey === 'census' && is_array($payload) && isset($payload['modules']) && is_array($payload['modules'])) {
            $payload['modules'] = collect($payload['modules'])
                ->map(function (array $module): array {
                    $module['key'] = $module['key'] ?? 'm_'.Str::lower(Str::random(6));
                    $questions = $module['questions'] ?? [];
                    $module['questions'] = collect(is_array($questions) ? $questions : [])
                        ->map(function (array $question): array {
                            $question['key'] = $question['key'] ?? 'q_'.Str::lower(Str::random(6));

                            return $question;
                        })
                        ->all();

                    return $module;
                })
                ->all();
        }

        return $payload;
    }
}
