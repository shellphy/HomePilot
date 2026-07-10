<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Matters\MatterTypeRegistry;
use App\Models\Matter;
use App\Models\Record;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * 管理端 · 事务：审核、发布（公告/征集等管理员专属类型）、编辑、删除。
 */
class MatterAdminController extends Controller
{
    /**
     * 全部事务（含待审核）；?pending=1 只看待审核队列。
     */
    public function index(Request $request): JsonResponse
    {
        $matters = Matter::query()
            ->when($request->boolean('pending'), fn ($query) => $query->where('is_approved', false))
            ->with('initiator.unit')
            ->withCount('joins')
            ->withCount(['records as register_count' => fn ($query) => $query->where('mode', Record::MODE_REGISTER)])
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
            ->loadCount(['records as register_count' => fn ($query) => $query->where('mode', Record::MODE_REGISTER)]);

        return response()->json(['data' => $this->present($matter)]);
    }

    /**
     * 管理员发布事务（任何类型，包括公告/征集这类业主不能自发的），默认直接公示。
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

        $matter->update([
            'title' => $validated['title'],
            'category' => $validated['category'] ?? $matter->category,
            'state' => $validated['state'] ?? $matter->state,
            'is_approved' => $validated['is_approved'] ?? $matter->is_approved,
            'target_count' => $validated['target_count'] ?? $matter->target_count,
            'payload' => array_merge($matter->payload ?? [], $this->payloadFrom($validated, $matter->type)),
        ]);

        return response()->json(['data' => $this->present($matter->refresh())]);
    }

    /**
     * 审核：通过（公示给全小区）或撤下。
     */
    public function approve(Request $request, Matter $matter): JsonResponse
    {
        $validated = $request->validate(['is_approved' => ['required', 'boolean']]);

        $matter->update($validated);

        return response()->json(['data' => $this->present($matter)]);
    }

    public function destroy(Matter $matter): JsonResponse
    {
        $matter->delete();

        return response()->json(['deleted' => true]);
    }

    /**
     * 登记明细（含楼栋/房号/微信/手机等仅管理员可见字段），答案换算成题面文字。
     */
    public function records(Matter $matter): JsonResponse
    {
        $questions = collect($matter->payloadValue('modules', []))
            ->flatMap(fn (array $module): array => $module['questions'] ?? [])
            ->keyBy('key');

        $records = $matter->records()
            ->where('mode', Record::MODE_REGISTER)
            ->with('resident.unit')
            ->latest()
            ->get()
            ->map(fn (Record $record): array => [
                'id' => $record->id,
                'unit_label' => $record->resident?->unit?->label ?? '',
                'room_label' => $record->resident?->room_label ?? '',
                'nickname' => $record->resident?->nickname ?? '',
                'wechat_id' => $record->resident?->wechat_id ?? '',
                'phone' => $record->resident?->phone ?? '',
                'created_at' => $record->created_at?->format('Y-m-d H:i'),
                'answers' => collect($record->payload['answers'] ?? [])
                    ->map(fn (mixed $value, string $key): array => [
                        'question' => $questions[$key]['text'] ?? $key,
                        'answer' => is_array($value) ? implode('、', $value) : (string) $value,
                    ])
                    ->values(),
            ]);

        return response()->json(['data' => $records]);
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
            'initiator' => $matter->initiator?->displayName() ?: '管理员发布',
            'join_count' => (int) ($matter->joins_count ?? 0),
            'register_count' => (int) ($matter->register_count ?? 0),
            'payload' => $matter->payload ?? (object) [],
            'created_at' => $matter->created_at?->format('Y-m-d H:i'),
        ];
    }

    /**
     * 管理端校验：通用字段 + 按类型的 payload 结构（征集问卷校验最严，答案按它落库）。
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
                'payload.modules.*.questions.*.type' => ['required', Rule::in(['single', 'multi'])],
                'payload.modules.*.questions.*.required' => ['sometimes', 'boolean'],
                'payload.modules.*.questions.*.options' => ['required', 'array', 'min:2'],
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

        if ($typeKey === 'census' && isset($payload['modules'])) {
            $payload['modules'] = collect($payload['modules'])
                ->map(function (array $module): array {
                    $module['key'] = $module['key'] ?? 'm_'.Str::lower(Str::random(6));
                    $module['questions'] = collect($module['questions'] ?? [])
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
