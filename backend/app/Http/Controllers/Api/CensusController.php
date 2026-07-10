<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesResident;
use App\Http\Controllers\Controller;
use App\Models\Matter;
use App\Models\Record;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * 征集事务的表态：问卷 schema 存在事务 payload 里（modules），
 * 本控制器完全通用——装修意向摸底、收房问题摸底、车位需求摸底共用。
 */
class CensusController extends Controller
{
    use ResolvesResident;

    /**
     * 下发问卷 schema + 我的答案 + 匿名聚合。
     */
    public function show(Request $request, Matter $matter): JsonResponse
    {
        abort_unless($matter->type === 'census', 404);

        $record = $this->recordOf($matter, $request);

        return response()->json([
            'title' => $matter->title,
            'state' => $matter->state,
            'pitch' => $matter->payloadValue('pitch', ''),
            'modules' => $matter->payloadValue('modules', []),
            'collects_contact' => (bool) $matter->payloadValue('collects_contact', false),
            'answers' => $record?->payload['answers'] ?? (object) [],
            'registered_count' => $matter->records()->where('mode', Record::MODE_REGISTER)->count(),
            'aggregates' => $this->aggregates($matter),
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

        if ($matter->payloadValue('collects_contact') && (! $resident->unit_id || $resident->wechat_id === '')) {
            throw ValidationException::withMessages([
                'profile' => '参与前请先在「我的 · 个人资料」里完善楼栋号和微信号',
            ]);
        }

        /** @var array<string, mixed> $answers */
        $answers = $request->validate(['answers' => ['required', 'array']]);

        $questions = $this->questions($matter);
        $this->validateAnswers($answers['answers'], $questions);

        $record = $this->recordOf($matter, $request);
        $merged = array_merge($record?->payload['answers'] ?? [], $answers['answers']);

        // 必答题在合并后必须齐全（保证基础模块先答）
        foreach ($questions as $key => $question) {
            if (($question['required'] ?? false) && ! array_key_exists($key, $merged)) {
                throw ValidationException::withMessages(['answers' => "「{$question['text']}」是必答题"]);
            }
        }

        if ($record) {
            $record->reviseTo(['answers' => $merged]);
        } else {
            $record = $matter->records()->create([
                'resident_id' => $resident->id,
                'mode' => Record::MODE_REGISTER,
                'payload' => ['answers' => $merged],
            ]);
        }

        return response()->json([
            'answered' => count($merged),
            'total' => $questions->count(),
            'registered_count' => $matter->records()->where('mode', Record::MODE_REGISTER)->count(),
        ], $record->wasRecentlyCreated ? 201 : 200);
    }

    private function recordOf(Matter $matter, Request $request): ?Record
    {
        return $matter->records()
            ->where('mode', Record::MODE_REGISTER)
            ->where('resident_id', $this->resident($request)->id)
            ->first();
    }

    /**
     * schema 摊平成 key => question 的映射。
     *
     * @return Collection<string, array{key: string, text: string, type: string, options: array<int, string>, required?: bool}>
     */
    private function questions(Matter $matter): Collection
    {
        return collect($matter->payloadValue('modules', []))
            ->flatMap(fn (array $module): array => $module['questions'] ?? [])
            ->keyBy('key');
    }

    /**
     * @param  array<string, mixed>  $answers
     * @param  Collection<string, array{key: string, text: string, type: string, options: array<int, string>}>  $questions
     */
    private function validateAnswers(array $answers, Collection $questions): void
    {
        foreach ($answers as $key => $value) {
            $question = $questions->get($key);

            if (! $question) {
                throw ValidationException::withMessages(['answers' => '包含未知的问题，请更新小程序后重试']);
            }

            $valid = $question['type'] === 'multi'
                ? is_array($value) && $value !== [] && array_diff($value, $question['options']) === []
                : is_string($value) && in_array($value, $question['options'], true);

            if (! $valid) {
                throw ValidationException::withMessages(['answers' => "「{$question['text']}」的答案无效"]);
            }
        }
    }

    /**
     * 匿名聚合：每道题的选项计数（公示面数据源）。
     *
     * @return array<int, array<string, mixed>>
     */
    private function aggregates(Matter $matter): array
    {
        $allAnswers = $matter->records()
            ->where('mode', Record::MODE_REGISTER)
            ->get()
            ->map(fn (Record $record): array => $record->payload['answers'] ?? []);

        return collect($matter->payloadValue('modules', []))
            ->map(fn (array $module): array => [
                'title' => $module['title'] ?? '',
                'questions' => collect($module['questions'] ?? [])->map(function (array $question) use ($allAnswers): array {
                    $values = $allAnswers
                        ->map(fn (array $answers) => $answers[$question['key']] ?? null)
                        ->filter()
                        ->flatMap(fn ($value): array => is_array($value) ? $value : [$value]);

                    return [
                        'key' => $question['key'],
                        'text' => $question['text'],
                        'counts' => $values->countBy()->sortDesc(),
                    ];
                })->values()->all(),
            ])
            ->values()
            ->all();
    }
}
