<?php

use App\Models\Matter;
use App\Models\Stance;

test('it seeds the custom furniture primer with a valid question schema and example answers', function () {
    $this->seed();

    $census = Matter::query()
        ->where('title', '全屋定制怎么选 · 答题即入门')
        ->sole();

    $modules = collect($census->payload['modules']);
    $questions = $modules->flatMap(fn (array $module): array => $module['questions']);

    expect($modules)->toHaveCount(7)
        ->and($questions)->toHaveCount(24)
        ->and($questions->pluck('key')->duplicates())->toBeEmpty()
        ->and($questions->pluck('key'))->toContain(
            'site_readiness',
            'kitchen_workflow',
            'appliance_coordination',
            'accessibility_safety',
            'drawing_acceptance',
        );

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

    $stances = Stance::query()
        ->where('matter_id', $census->id)
        ->where('mode', Stance::MODE_REGISTER)
        ->get();

    expect($stances)->toHaveCount(40);

    $stances->each(function (Stance $stance) use ($validOptions): void {
        foreach ($stance->payload['answers'] as $key => $answer) {
            expect($validOptions)->toHaveKey($key);

            foreach ((array) $answer as $selectedOption) {
                expect($validOptions[$key])->toContain($selectedOption);
            }
        }
    });
});
