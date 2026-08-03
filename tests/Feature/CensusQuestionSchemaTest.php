<?php

use App\Models\Matter;
use App\Models\Resident;
use App\Models\Stance;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;

test('census questions reject fields outside the published schema', function () {
    Sanctum::actingAs(Resident::factory()->admin()->create());

    $this->postJson('/api/matters', [
        'type' => 'census',
        'title' => '定制柜摸底',
        'modules' => [[
            'title' => '柜体',
            'questions' => [[
                'text' => '柜体倾向哪种板材？',
                'type' => 'single',
                'options' => ['颗粒板', '多层实木', '还没概念'],
                'metadata' => ['source' => 'unknown'],
            ]],
        ]],
    ])->assertJsonValidationErrors('modules.0.questions.0');
});

test('census modules and questions reject removed descriptions', function () {
    Sanctum::actingAs(Resident::factory()->admin()->create());

    $this->postJson('/api/matters', [
        'type' => 'census',
        'title' => '定制柜摸底',
        'modules' => [[
            'title' => '柜体',
            'intro' => '先确定柜体材料。',
            'questions' => [[
                'text' => '柜体倾向哪种板材？',
                'type' => 'single',
                'note' => '板材影响环保和防潮。',
                'options' => ['颗粒板', '多层实木'],
            ]],
        ]],
    ])->assertJsonValidationErrors([
        'modules.0',
        'modules.0.questions.0',
    ]);
});

test('the data migration removes answer explanations without changing existing answers', function () {
    $census = Matter::factory()->create([
        'type' => 'census',
        'state' => 'open',
        'payload' => [
            'modules' => [[
                'key' => 'm1',
                'title' => '柜体',
                'questions' => [[
                    'key' => 'q1',
                    'text' => '柜体倾向哪种板材？',
                    'type' => 'single',
                    'options' => ['颗粒板', '多层实木'],
                    'option_notes' => ['便宜，环保看等级', '贵约三成，更防潮'],
                ]],
            ]],
        ],
    ]);
    $stance = $census->stances()->create([
        'resident_id' => Resident::factory()->create()->id,
        'mode' => Stance::MODE_REGISTER,
        'payload' => ['answers' => ['q1' => '颗粒板']],
    ]);
    Log::spy();

    $migration = require database_path('migrations/2026_08_03_125648_remove_option_notes_from_census_payloads.php');
    $migration->up();

    $question = $census->refresh()->payloadValue('modules')[0]['questions'][0];

    expect($question)->not->toHaveKey('option_notes')
        ->and($stance->refresh()->payload['answers'])->toBe(['q1' => '颗粒板'])
        ->and($census->stances()->count())->toBe(1);

    Log::shouldHaveReceived('info')
        ->once()
        ->with('问卷答案说明清理完成', [
            'matters_updated' => 1,
            'questions_updated' => 1,
        ]);
});

test('the data migration removes module and question descriptions without changing existing answers', function () {
    $census = Matter::factory()->create([
        'type' => 'census',
        'state' => 'open',
        'payload' => [
            'modules' => [[
                'key' => 'm1',
                'title' => '柜体',
                'intro' => '先确定柜体材料。',
                'questions' => [[
                    'key' => 'q1',
                    'text' => '柜体倾向哪种板材？',
                    'type' => 'single',
                    'note' => '板材影响环保和防潮。',
                    'options' => ['颗粒板', '多层实木'],
                ]],
            ]],
        ],
    ]);
    $stance = $census->stances()->create([
        'resident_id' => Resident::factory()->create()->id,
        'mode' => Stance::MODE_REGISTER,
        'payload' => ['answers' => ['q1' => '颗粒板']],
    ]);
    Log::spy();

    $migration = require database_path('migrations/2026_08_03_132113_remove_census_descriptions_from_payloads.php');
    $migration->up();

    $module = $census->refresh()->payloadValue('modules')[0];

    expect($module)->not->toHaveKey('intro')
        ->and($module['questions'][0])->not->toHaveKey('note')
        ->and($stance->refresh()->payload['answers'])->toBe(['q1' => '颗粒板'])
        ->and($census->stances()->count())->toBe(1);

    Log::shouldHaveReceived('info')
        ->once()
        ->with('问卷说明清理完成', [
            'matters_updated' => 1,
            'modules_updated' => 1,
            'questions_updated' => 1,
        ]);
});
