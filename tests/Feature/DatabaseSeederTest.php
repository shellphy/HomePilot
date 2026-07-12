<?php

use App\Models\Matter;
use App\Models\Stance;

test('it seeds exactly five renovation questionnaires with valid schemas and example answers', function () {
    $this->seed();

    $expectedQuestionCounts = [
        '硬装怎么做 · 答题即入门' => 32,
        '中央空调怎么选 · 答题即入门' => 15,
        '全屋定制怎么选 · 答题即入门' => 24,
        '软装怎么搭 · 答题即入门' => 19,
        '全屋需求摸底 · 说说你家怎么住' => 26,
    ];

    $censuses = Matter::query()
        ->whereIn('title', array_keys($expectedQuestionCounts))
        ->get()
        ->keyBy('title');

    expect($censuses)->toHaveCount(5)
        ->and(Matter::query()->where('title', '装修意向摸底 · 全小区征集中')->exists())->toBeFalse();

    foreach ($censuses as $title => $census) {
        $questions = collect($census->payload['modules'])
            ->flatMap(fn (array $module): array => $module['questions']);

        expect($questions)->toHaveCount($expectedQuestionCounts[$title])
            ->and($questions->pluck('key')->duplicates())->toBeEmpty();

        $questions->each(function (array $question): void {
            expect($question)->toHaveKeys(['key', 'text', 'type']);

            if ($question['type'] !== 'text') {
                expect($question)->toHaveKey('options')
                    ->and($question['options'])->not->toBeEmpty();
            }

            if (isset($question['option_notes'])) {
                expect($question['option_notes'])->toHaveCount(count($question['options']));
            }
        });

        $questionMap = $questions->keyBy('key');

        Stance::query()
            ->where('matter_id', $census->id)
            ->where('mode', Stance::MODE_REGISTER)
            ->each(function (Stance $stance) use ($questionMap): void {
                foreach ($stance->payload['answers'] as $key => $answer) {
                    expect($questionMap)->toHaveKey($key);

                    if ($questionMap[$key]['type'] === 'text') {
                        expect($answer)->toBeString()->not->toBeEmpty();

                        continue;
                    }

                    foreach ((array) $answer as $selectedOption) {
                        expect($questionMap[$key]['options'])->toContain($selectedOption);
                    }
                }
            });
    }

    $hardFinishQuestions = collect($censuses['硬装怎么做 · 答题即入门']->payload['modules'])
        ->flatMap(fn (array $module): array => $module['questions']);

    expect($hardFinishQuestions)->toHaveCount(32)
        ->and($hardFinishQuestions->pluck('key'))->toContain(
            'handover_check',
            'trade_coordination',
            'soundproofing',
            'tile_layout_acceptance',
            'lighting',
        );
});
