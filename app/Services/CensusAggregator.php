<?php

namespace App\Services;

use App\Models\Matter;
use App\Models\Stance;

class CensusAggregator
{
    public const MINIMUM_PUBLIC_RESPONSES = 5;

    /** @return array<int, array{title: string, questions: array<int, array<string, mixed>>}> */
    public function for(Matter $matter): array
    {
        $stances = $matter->relationLoaded('stances')
            ? $matter->stances->where('mode', Stance::MODE_REGISTER)
            : $matter->stances()->where('mode', Stance::MODE_REGISTER)->get();
        $allAnswers = $stances->map(fn (Stance $stance): array => $stance->payload['answers'] ?? []);
        $aggregates = [];

        foreach ($matter->payloadList('modules') as $module) {
            if (! is_array($module)) {
                continue;
            }

            $rawQuestions = $module['questions'] ?? [];
            $questions = is_array($rawQuestions) ? $rawQuestions : [];
            $presentedQuestions = [];

            foreach ($questions as $question) {
                if (! is_array($question)) {
                    continue;
                }

                $key = (string) ($question['key'] ?? '');
                $text = (string) ($question['text'] ?? '');

                // 填空题不进公示聚合：原文永不公示，选项类才有可统计的分布
                if (($question['type'] ?? '') === 'text') {
                    continue;
                }

                $values = $allAnswers
                    ->map(fn (array $answers) => $answers[$key] ?? null)
                    ->filter()
                    ->flatMap(fn ($value): array => is_array($value) ? $value : [$value]);

                /** @var array<string, int> $counts */
                $counts = $values->countBy()->sortDesc()->all();
                $presentedQuestions[] = [
                    'key' => $key,
                    'text' => $text,
                    'counts' => $counts,
                ];
            }

            $aggregates[] = [
                'title' => (string) ($module['title'] ?? ''),
                'questions' => $presentedQuestions,
            ];
        }

        return $aggregates;
    }
}
