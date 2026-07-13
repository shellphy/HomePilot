<?php

use App\Models\Matter;
use App\Models\Resident;

test('it seeds the custom furniture primer with a valid question schema', function () {
    Resident::factory()->create();

    $this->seed();

    $census = Matter::query()
        ->where('title', '全屋定制怎么选 · 答题即入门')
        ->sole();

    $modules = collect($census->payload['modules']);
    $questions = $modules->flatMap(fn (array $module): array => $module['questions']);

    expect($modules)->toHaveCount(7)
        ->and($questions)->toHaveCount(25)
        ->and($questions->pluck('key')->duplicates())->toBeEmpty()
        ->and($questions->pluck('key'))->toContain(
            'site_readiness',
            'kitchen_workflow',
            'wardrobe_fittings',
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

});
