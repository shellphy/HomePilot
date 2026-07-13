<?php

use App\Models\Resident;
use App\Models\Stance;
use Laravel\Sanctum\Sanctum;

test('open census results stay hidden until the resident answers and the privacy threshold is met', function () {
    $resident = Resident::factory()->create();
    $census = renovationCensus(['payload' => [
        'modules' => [[
            'key' => 'one',
            'title' => '选择',
            'questions' => [[
                'key' => 'choice', 'text' => '你选什么？', 'type' => 'single', 'options' => ['A', 'B'],
            ]],
        ]],
    ]]);
    Stance::factory()->count(5)->for($census, 'matter')->create([
        'mode' => Stance::MODE_REGISTER,
        'payload' => ['answers' => ['choice' => 'A']],
    ]);
    Sanctum::actingAs($resident);

    $this->getJson("/api/matters/{$census->id}/census")
        ->assertJsonPath('aggregates_visible', false)
        ->assertJsonPath('aggregates', []);

    Stance::factory()->for($census, 'matter')->for($resident, 'resident')->create([
        'mode' => Stance::MODE_REGISTER,
        'payload' => ['answers' => ['choice' => 'B']],
    ]);

    $this->getJson("/api/matters/{$census->id}/census")
        ->assertJsonPath('aggregates_visible', true)
        ->assertJsonPath('aggregates.0.questions.0.counts.A', 5);
});

test('census overview returns all cards and previews in one response', function () {
    $resident = Resident::factory()->create();
    $census = renovationCensus();
    Stance::factory()->count(5)->censusAnswers()->for($census, 'matter')->create();
    Stance::factory()->censusAnswers()->for($census, 'matter')->for($resident, 'resident')->create();
    Sanctum::actingAs($resident);

    $this->getJson('/api/censuses/overview')
        ->assertSuccessful()
        ->assertJsonPath('data.0.id', $census->id)
        ->assertJsonPath('data.0.aggregates_visible', true)
        ->assertJsonPath('data.0.my_answered', 3)
        ->assertJsonStructure(['data' => [['top' => ['question', 'label', 'count']]]]);
});
