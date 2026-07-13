<?php

namespace App\Services;

use App\Models\Matter;
use App\Models\Stance;

class CensusAggregator
{
    public const MINIMUM_PUBLIC_RESPONSES = 5;

    /** @return array<int, array<string, mixed>> */
    public function for(Matter $matter): array
    {
        $stances = $matter->relationLoaded('stances')
            ? $matter->stances->where('mode', Stance::MODE_REGISTER)
            : $matter->stances()->where('mode', Stance::MODE_REGISTER)->get();
        $allAnswers = $stances->map(fn (Stance $stance): array => $stance->payload['answers'] ?? []);
        $summaries = $matter->payloadValue('text_summaries', []);

        return collect($matter->payloadList('modules'))
            ->map(fn (array $module): array => [
                'title' => $module['title'] ?? '',
                'questions' => collect($module['questions'] ?? [])
                    ->map(function (array $question) use ($allAnswers, $summaries): ?array {
                        if (($question['type'] ?? '') === 'text') {
                            $summary = $summaries[$question['key']] ?? null;

                            return ($summary['published'] ?? false) ? [
                                'key' => $question['key'],
                                'text' => $question['text'],
                                'themes' => $summary['themes'],
                            ] : null;
                        }

                        $values = $allAnswers
                            ->map(fn (array $answers) => $answers[$question['key']] ?? null)
                            ->filter()
                            ->flatMap(fn ($value): array => is_array($value) ? $value : [$value]);

                        return [
                            'key' => $question['key'],
                            'text' => $question['text'],
                            'counts' => $values->countBy()->sortDesc(),
                        ];
                    })
                    ->filter()
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }
}
