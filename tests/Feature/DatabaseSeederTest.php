<?php

use App\Models\Matter;
use App\Models\Stance;

test('it seeds both primer censuses with valid question schemas and example answers', function () {
    $this->seed();

    $censuses = Matter::query()
        ->whereIn('title', ['硬装怎么做 · 答题即入门', '中央空调怎么选 · 答题即入门'])
        ->get()
        ->keyBy('title');

    expect($censuses)->toHaveCount(2);

    foreach ($censuses as $census) {
        $questions = collect($census->payload['modules'])
            ->flatMap(fn (array $module): array => $module['questions']);

        expect($questions->pluck('key')->duplicates())->toBeEmpty();

        $questions->each(function (array $question): void {
            expect($question)->toHaveKeys(['key', 'text', 'type', 'options'])
                ->and($question['options'])->not->toBeEmpty();

            if (isset($question['option_notes'])) {
                expect($question['option_notes'])->toHaveCount(count($question['options']));
            }
        });

        $validOptions = $questions->mapWithKeys(
            fn (array $question): array => [$question['key'] => $question['options']],
        );

        Stance::query()
            ->where('matter_id', $census->id)
            ->where('mode', Stance::MODE_REGISTER)
            ->each(function (Stance $stance) use ($validOptions): void {
                foreach ($stance->payload['answers'] as $key => $answer) {
                    expect($validOptions)->toHaveKey($key);

                    foreach ((array) $answer as $selectedOption) {
                        expect($validOptions[$key])->toContain($selectedOption);
                    }
                }
            });
    }

    $hardFinishQuestions = collect($censuses['硬装怎么做 · 答题即入门']->payload['modules'])
        ->flatMap(fn (array $module): array => $module['questions']);

    expect($hardFinishQuestions)->toHaveCount(26)
        ->and($hardFinishQuestions->pluck('key'))->toContain('handover_check', 'soundproofing', 'lighting');
});
