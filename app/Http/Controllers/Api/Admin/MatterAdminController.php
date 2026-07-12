<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\MatterReviewStatus;
use App\Events\MatterApproved;
use App\Events\MatterRejected;
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
            ->when($request->boolean('pending'), fn ($query) => $query->where('review_status', MatterReviewStatus::Pending->value))
            ->with(['initiator', 'initiatorParty'])
            ->withCount('joins')
            ->withCount(['stances as register_count' => fn ($query) => $query->where('mode', Stance::MODE_REGISTER)])
            ->latest()
            ->get();

        return response()->json([
            // 只数真正待处理的（驳回态在等发起人修改，不占管理员的队列徽标）
            'data' => $matters->map(fn (Matter $matter): array => $this->present($matter)),
            'pending_count' => Matter::where('review_status', MatterReviewStatus::Pending->value)->count(),
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
            'initiator_party_id' => $validated['initiator_party_id'] ?? null,
            'title' => $validated['title'],
            'category' => $validated['category'] ?? '',
            'state' => $validated['state'] ?? MatterTypeRegistry::for($typeKey)->initialState(),
            // 管理员代发默认直接公示；显式传 is_approved=false 则进待审核队列
            'review_status' => ($validated['is_approved'] ?? true) ? MatterReviewStatus::Approved : MatterReviewStatus::Pending,
            'target_count' => $validated['target_count'] ?? 0,
            'related_matter_id' => $validated['related_matter_id'] ?? null,
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

        $attributes = [
            'title' => $validated['title'],
            'category' => $validated['category'] ?? $matter->category,
            'state' => $validated['state'] ?? $matter->state,
            'target_count' => $validated['target_count'] ?? $matter->target_count,
            // 显式传 null 表示解除挂靠/去署名，键缺失才保留原值
            'related_matter_id' => array_key_exists('related_matter_id', $validated)
                ? $validated['related_matter_id']
                : $matter->related_matter_id,
            'initiator_party_id' => array_key_exists('initiator_party_id', $validated)
                ? $validated['initiator_party_id']
                : $matter->initiator_party_id,
            'payload' => array_merge($matter->payload ?? [], $this->payloadFrom($validated, $matter->type)),
        ];

        // 「是否公示」开关：勾上→通过公示；把已公示的撤下→回待审核；
        // 待审/驳回态只编辑其它字段时保持原状态，不误清驳回理由
        if (array_key_exists('is_approved', $validated)) {
            if ($validated['is_approved']) {
                $attributes['review_status'] = MatterReviewStatus::Approved;
                $attributes['reject_reason'] = '';
            } elseif ($matter->is_approved) {
                $attributes['review_status'] = MatterReviewStatus::Pending;
            }
        }

        $matter->update($attributes);

        if ($matter->state !== $previousState) {
            $admin = $this->resident($request);
            $matter->recordActivity($admin);
            MatterStateChanged::dispatch($matter, $previousState, $admin);
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

        if ($validated['is_approved']) {
            $matter->approve();
        } else {
            $matter->reject($validated['reason'] ?? '');
        }

        // 审核结果对发起人是关键动态：喂给「我的」页未读红点。
        // 通过只在状态真的翻正时记；驳回每次都记（理由可能更新）。
        if ($matter->is_approved && ! $wasApproved) {
            $matter->recordActivity($this->resident($request));
            MatterApproved::dispatch($matter);
        }

        if (! $matter->is_approved) {
            $matter->recordActivity($this->resident($request));
            MatterRejected::dispatch($matter);
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
                    'layout_label' => $stance->resident->layout_label,
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
            'state_label' => $type->stateLabel($matter->state),
            // 管理端可选全部状态（含旁路终态），作为纠错通道
            'states' => $type->allStates(),
            'is_approved' => $matter->is_approved,
            'review_status' => $matter->review_status->value,
            'review_status_label' => $matter->review_status->label(),
            'reject_reason' => $matter->reject_reason,
            'target_count' => $matter->target_count,
            'initiator' => $this->initiatorLabel($matter),
            'initiator_party_id' => $matter->initiator_party_id,
            'related_matter_id' => $matter->related_matter_id,
            'join_count' => (int) ($matter->joins_count ?? 0),
            'register_count' => (int) ($matter->register_count ?? 0),
            'payload' => $matter->payload ?? (object) [],
            'created_at' => $matter->created_at?->format('Y-m-d H:i'),
        ];
    }

    /**
     * 管理端校验：通用字段 + 按类型的 payload 结构（征集问卷校验最严，答案按它落库）。
     * 列级字段（category/target_count）与 payload 字段都直接复用业主前台的
     * baseRules/payloadRules：管理员代发的团购同样必须有品类与目标人数，
     * 字段必填与字数上限两条路径永远一致，不会各改各的漂移开。
     *
     * @return array<string, mixed>
     */
    private function rules(string $typeKey): array
    {
        $type = MatterTypeRegistry::for($typeKey);

        $rules = [
            'title' => ['required', 'string', 'max:60'],
            'category' => ['sometimes', 'nullable', 'string', 'max:30'],
            'state' => ['sometimes', Rule::in(array_keys($type->allStates()))],
            'is_approved' => ['sometimes', 'boolean'],
            'target_count' => ['sometimes', 'integer', 'min:0'],
            ...$type->baseRules(),
            'payload' => ['sometimes', 'array'],
        ];

        foreach ($type->payloadRules() as $key => $rule) {
            $rules["payload.{$key}"] = $rule;
        }

        if ($typeKey === 'census') {
            $rules += [
                // 配套问卷：征集可挂到一个团购上，团购详情页展示问卷入口
                'related_matter_id' => ['sometimes', 'nullable', Rule::exists('matters', 'id')->where('type', 'groupbuy')->whereNull('deleted_at')],
                // 署名发起：物业/业委会/商家想做的调研由管理员代建并亮明发起方，结果对全小区公开
                'initiator_party_id' => ['sometimes', 'nullable', Rule::exists('parties', 'id')],
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
                // 选项解释（与 options 平行的数组，答案仍只存选项本身）：答题即建概念
                'payload.modules.*.questions.*.option_notes' => ['sometimes', 'array'],
                'payload.modules.*.questions.*.option_notes.*' => ['nullable', 'string', 'max:100'],
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
