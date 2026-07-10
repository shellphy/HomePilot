<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesResident;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SurveyController extends Controller
{
    use ResolvesResident;

    /**
     * 下发问卷题目 + 我已填的答案（用于回显和断点续填）。
     */
    public function show(Request $request): JsonResponse
    {
        $registration = $this->resident($request)->registration;

        return response()->json([
            'modules' => config('homepilot.survey'),
            'answers' => $registration->answers ?? (object) [],
        ]);
    }

    /**
     * 保存一批答案（按模块提交，与已有答案合并）。
     */
    public function store(Request $request): JsonResponse
    {
        $resident = $this->resident($request);
        $registration = $resident->registration;

        if (! $registration) {
            throw ValidationException::withMessages(['answers' => '请先完成基础登记']);
        }

        /** @var array<string, mixed> $answers */
        $answers = $request->validate(['answers' => ['required', 'array']])['answers'];

        $questions = $this->questions();

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

        $registration->update(['answers' => array_merge($registration->answers ?? [], $answers)]);

        return response()->json([
            'answered' => count($registration->answers ?? []),
            'total' => $questions->count(),
        ]);
    }

    /**
     * 题库摊平成 key => question 的映射。
     *
     * @return Collection<string, array{key: string, text: string, type: string, options: array<int, string>}>
     */
    private function questions(): Collection
    {
        /** @var array<int, array{key: string, title: string, questions: array<int, array{key: string, text: string, type: string, options: array<int, string>}>}> $modules */
        $modules = config('homepilot.survey');

        return collect($modules)
            ->flatMap(fn (array $module): array => $module['questions'])
            ->keyBy('key');
    }
}
