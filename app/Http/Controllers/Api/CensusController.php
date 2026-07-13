<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesResident;
use App\Http\Controllers\Controller;
use App\Matters\CensusReportPresentation;
use App\Models\Matter;
use App\Models\Stance;
use App\Services\CensusAggregator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * 征集事项的表态：问卷 schema 存在事项 payload 里（modules），
 * 本控制器完全通用——装修意向摸底、收房问题摸底、车位需求摸底共用。
 */
class CensusController extends Controller
{
    use ResolvesResident;

    public function __construct(
        private CensusReportPresentation $presentation,
        private CensusAggregator $aggregator,
    ) {}

    /**
     * 下发问卷 schema + 我的答案 + 匿名聚合。
     */
    public function show(Request $request, Matter $matter): JsonResponse
    {
        abort_unless($matter->type === 'census', 404);

        $resident = $this->resident($request);
        $stance = $this->stanceOf($matter, $request);

        $registeredCount = $matter->stances()->where('mode', Stance::MODE_REGISTER)->count();
        $aggregatesVisible = $registeredCount >= CensusAggregator::MINIMUM_PUBLIC_RESPONSES
            && ($stance !== null || $matter->state !== 'open');

        return response()->json([
            'title' => $matter->title,
            'state' => $matter->state,
            'body' => $matter->body ?? '',
            'purpose' => $matter->payloadValue('purpose', ''),
            'report_presentation' => $this->presentation->for($matter),
            'modules' => $matter->payloadValue('modules', []),
            'collects_contact' => (bool) $matter->payloadValue('collects_contact', false),
            'answers' => $stance?->payload['answers'] ?? (object) [],
            'registered_count' => $registeredCount,
            'aggregates_visible' => $aggregatesVisible,
            'aggregates_minimum' => CensusAggregator::MINIMUM_PUBLIC_RESPONSES,
            'aggregates' => $aggregatesVisible ? $this->aggregator->for($matter) : [],
            // 署名发起（物业/业委会/商家的调研）：亮明身份是开放发起权的前提
            'initiator_party' => $matter->initiatorParty ? [
                'label' => $matter->initiatorParty->typeLabel(),
                'name' => $matter->initiatorParty->name,
            ] : null,
            // 我是不是这份征集的发起者本人（决定要不要露出「看邻居授权登记」入口）
            'is_initiator' => $matter->initiator_id === $resident->id,
            // 我这次问卷有没有勾选「让发起者看到我的问卷」（回填勾选框，修改问卷时保持上次选择）
            'my_visible_to_initiator' => (bool) ($stance?->payload['visible_to_initiator'] ?? false),
        ]);
    }

    /**
     * 提交一批答案（按模块提交，与已有答案合并，走修订链）。
     * 表单只管答案；联系方式是成员档案（「我的 · 个人资料」维护），
     * collects_contact 的征集只把"档案完整"作为参与的前置条件。
     */
    public function store(Request $request, Matter $matter): JsonResponse
    {
        abort_unless($matter->type === 'census', 404);
        abort_unless($matter->state === 'open', 422, '该征集已结束');

        $resident = $this->resident($request);

        if ($matter->payloadValue('collects_contact') && ($resident->unit_label === '' || $resident->phone === '')) {
            throw ValidationException::withMessages([
                'profile' => '参与前请先在「我的 · 个人资料」里选好楼栋号并授权手机号',
            ]);
        }

        /** @var array{answers: array<string, mixed>, visible_to_initiator?: bool} $validated */
        $validated = $request->validate([
            'answers' => ['required', 'array'],
            // 参与者主动授权：勾选后本份征集的发起者本人能看到我的信息+逐题答案（默认关）
            'visible_to_initiator' => ['sometimes', 'boolean'],
        ]);

        $questions = collect($matter->payloadList('modules'))
            ->flatMap(fn (array $module): array => $module['questions'] ?? [])
            ->keyBy('key');
        $this->validateAnswers($validated['answers'], $questions);

        $stance = $this->stanceOf($matter, $request);
        $merged = array_merge($stance?->payload['answers'] ?? [], $validated['answers']);

        $payload = ['answers' => $merged];
        // 授权标记随答案一起进 payload：本次带了就更新，没带则沿用上次选择（默认 false）
        if ($request->has('visible_to_initiator')) {
            $payload['visible_to_initiator'] = $request->boolean('visible_to_initiator');
        } elseif ($stance !== null && array_key_exists('visible_to_initiator', $stance->payload ?? [])) {
            $payload['visible_to_initiator'] = (bool) $stance->payload['visible_to_initiator'];
        }

        if ($stance) {
            $stance->reviseTo($payload);
        } else {
            $stance = $matter->stances()->create([
                'resident_id' => $resident->id,
                'mode' => Stance::MODE_REGISTER,
                'payload' => $payload,
            ]);
        }

        return response()->json([
            'answered' => count($merged),
            'total' => $questions->count(),
            'registered_count' => $matter->stances()->where('mode', Stance::MODE_REGISTER)->count(),
        ], $stance->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * 「让发起者看到我的问卷」授权开关：在「查看我的问卷」页冷静态设置，独立于答题提交。
     */
    public function consent(Request $request, Matter $matter): JsonResponse
    {
        abort_unless($matter->type === 'census', 404);

        $validated = $request->validate([
            'visible_to_initiator' => ['required', 'boolean'],
        ]);

        $resident = $this->resident($request);
        $stance = $matter->stances()
            ->where('mode', Stance::MODE_REGISTER)
            ->where('resident_id', $resident->id)
            ->first();
        abort_unless($stance !== null, 422, '先参与问卷再设置授权');

        $stance->reviseTo(array_merge($stance->payload ?? [], [
            'visible_to_initiator' => $validated['visible_to_initiator'],
        ]));

        return response()->json(['visible_to_initiator' => $validated['visible_to_initiator']]);
    }

    /**
     * 发起者视图：只列出主动勾选「让发起者看到我的问卷」的参与者，
     * 含显示名、手机号（限收联系方式的征集且业主已授权）、逐题答案（换算成题面文字）。
     * 邻居授权的对象是发起者本人。
     */
    public function consented(Request $request, Matter $matter): JsonResponse
    {
        abort_unless($matter->type === 'census', 404);

        $resident = $this->resident($request);
        abort_unless($matter->initiator_id === $resident->id, 403);

        $questions = collect($matter->payloadList('modules'))
            ->flatMap(fn (array $module): array => $module['questions'] ?? [])
            ->keyBy('key');

        $collectsContact = (bool) $matter->payloadValue('collects_contact', false);

        $consented = $matter->stances()
            ->where('mode', Stance::MODE_REGISTER)
            ->where('payload->visible_to_initiator', true)
            ->with('resident')
            ->latest()
            ->get()
            ->map(fn (Stance $stance): array => [
                'id' => $stance->id,
                'name' => $stance->resident->displayName(),
                'phone' => $collectsContact ? $stance->resident->phone : '',
                'created_at' => $stance->created_at?->format('Y-m-d H:i'),
                'answers' => $this->readableAnswers($stance, $questions),
            ]);

        return response()->json(['data' => $consented]);
    }

    /**
     * 把一份问卷的答案 key 换算成题面文字（多选顿号连接，填空出原文）。
     *
     * @param  Collection<array-key, array<string, mixed>>  $questions
     * @return Collection<int, array{question: string, answer: string}>
     */
    private function readableAnswers(Stance $stance, Collection $questions): Collection
    {
        $answers = $stance->payload['answers'] ?? [];

        return collect(is_array($answers) ? $answers : [])
            ->map(fn (mixed $value, int|string $key): array => [
                'question' => (string) ($questions[$key]['text'] ?? $key),
                'answer' => is_array($value) ? implode('、', $value) : (string) $value,
            ])
            ->values();
    }

    private function stanceOf(Matter $matter, Request $request): ?Stance
    {
        return $matter->stances()
            ->where('mode', Stance::MODE_REGISTER)
            ->where('resident_id', $this->resident($request)->id)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $answers
     * @param  Collection<array-key, array{key: string, text: string, type: string, options?: array<int, string>}>  $questions
     */
    private function validateAnswers(array $answers, Collection $questions): void
    {
        foreach ($answers as $key => $value) {
            $question = $questions->get($key);

            if (! $question) {
                throw ValidationException::withMessages(['answers' => '包含未知的问题，请更新小程序后重试']);
            }

            $valid = match ($question['type']) {
                'text' => is_string($value) && trim($value) !== '' && mb_strlen($value) <= 500,
                'multi' => is_array($value) && $value !== [] && array_diff($value, $question['options'] ?? []) === [],
                default => is_string($value) && in_array($value, $question['options'] ?? [], true),
            };

            if (! $valid) {
                throw ValidationException::withMessages(['answers' => "「{$question['text']}」的答案无效"]);
            }
        }
    }
}
