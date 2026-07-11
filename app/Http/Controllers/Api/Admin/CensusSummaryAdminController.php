<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Matter;
use App\Models\Stance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * 管理端 · 征集文本题：匿名明细浏览 + 人工归纳（主题/条数/概括）。
 * 归纳存事项 payload 的 text_summaries.{questionKey}，发布后进公示面（CensusController 的 aggregates）。
 */
class CensusSummaryAdminController extends Controller
{
    /**
     * 每道填空题的全部匿名答案（倒序、无身份字段）+ 已有归纳。
     */
    public function show(Matter $matter): JsonResponse
    {
        abort_unless($matter->type === 'census', 404);

        $allAnswers = $matter->stances()
            ->where('mode', Stance::MODE_REGISTER)
            ->latest()
            ->get()
            ->map(fn (Stance $stance): array => $stance->payload['answers'] ?? []);

        $summaries = $matter->payloadValue('text_summaries', []);

        $questions = collect($matter->payloadList('modules'))
            ->flatMap(function (array $module): array {
                $questions = $module['questions'] ?? [];

                return collect(is_array($questions) ? $questions : [])
                    ->filter(fn (array $question): bool => ($question['type'] ?? '') === 'text')
                    ->map(fn (array $question): array => [
                        'key' => $question['key'],
                        'text' => $question['text'],
                        'module_title' => $module['title'] ?? '',
                    ])
                    ->all();
            })
            ->map(fn (array $question): array => $question + [
                'answers' => $allAnswers
                    ->map(fn (array $answers) => $answers[$question['key']] ?? null)
                    ->filter(fn ($answer): bool => is_string($answer) && trim($answer) !== '')
                    ->values(),
                'summary' => $summaries[$question['key']] ?? null,
            ])
            ->values();

        return response()->json([
            'title' => $matter->title,
            'questions' => $questions,
        ]);
    }

    /**
     * 保存某道文本题的归纳；published=true 时公示，false 为仅管理员可见的草稿。
     */
    public function update(Request $request, Matter $matter): JsonResponse
    {
        abort_unless($matter->type === 'census', 404);

        $validated = $request->validate([
            'question_key' => ['required', 'string'],
            'themes' => ['sometimes', 'array', 'max:20'],
            'themes.*.title' => ['required', 'string', 'max:30'],
            'themes.*.count' => ['required', 'integer', 'min:0'],
            'themes.*.note' => ['sometimes', 'nullable', 'string', 'max:200'],
            'published' => ['required', 'boolean'],
        ]);

        $isTextQuestion = collect($matter->payloadList('modules'))
            ->flatMap(fn (array $module): array => $module['questions'] ?? [])
            ->contains(fn (array $question): bool => ($question['key'] ?? null) === $validated['question_key']
                && ($question['type'] ?? '') === 'text');

        if (! $isTextQuestion) {
            throw ValidationException::withMessages(['question_key' => '不是本征集的填空题']);
        }

        $rawThemes = $validated['themes'] ?? [];
        $themes = collect(is_array($rawThemes) ? $rawThemes : [])
            ->map(fn (array $theme): array => [
                'title' => $theme['title'],
                'count' => $theme['count'],
                'note' => $theme['note'] ?? '',
            ])
            ->all();

        if ($validated['published'] && $themes === []) {
            throw ValidationException::withMessages(['themes' => '发布前请先写至少一个主题']);
        }

        $summaries = $matter->payloadValue('text_summaries', []);
        $summaries[$validated['question_key']] = [
            'themes' => $themes,
            'published' => $validated['published'],
        ];

        $matter->update([
            'payload' => array_merge($matter->payload ?? [], ['text_summaries' => $summaries]),
        ]);

        return response()->json(['data' => $summaries[$validated['question_key']]]);
    }
}
