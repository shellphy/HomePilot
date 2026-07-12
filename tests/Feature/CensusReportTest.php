<?php

use App\Ai\Agents\CensusReportGenerator;
use App\Models\Matter;
use App\Models\Resident;
use App\Models\Stance;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Sanctum\Sanctum;

function reportCensus(Resident $resident): Matter
{
    $matter = Matter::factory()->create([
        'type' => 'census',
        'title' => '全屋需求摸底',
        'payload' => [
            'purpose' => '帮业主明确装修需求',
            'modules' => [[
                'key' => 'family',
                'title' => '家庭',
                'questions' => [[
                    'key' => 'elderly',
                    'text' => '是否有老人常住？',
                    'type' => 'single',
                    'options' => ['有', '没有'],
                ]],
            ]],
        ],
    ]);

    Stance::factory()->for($matter, 'matter')->for($resident, 'resident')->create([
        'mode' => Stance::MODE_REGISTER,
        'payload' => ['answers' => ['elderly' => '有']],
    ]);

    return $matter;
}

test('a resident generates and reuses a structured census report', function () {
    CensusReportGenerator::fake();
    $resident = Resident::factory()->create(['layout_label' => '130㎡']);
    $matter = reportCensus($resident);
    Sanctum::actingAs($resident);

    $first = $this->postJson("/api/matters/{$matter->id}/census-report")
        ->assertSuccessful()
        ->assertJsonStructure(['report' => ['headline', 'overview', 'priorities', 'decisions', 'open_questions', 'red_flags', 'merchant_brief']]);

    $this->postJson("/api/matters/{$matter->id}/census-report")
        ->assertSuccessful()
        ->assertJsonPath('report.headline', $first->json('report.headline'));

    CensusReportGenerator::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('130㎡') && $prompt->contains('老人'));
});

test('a report can be shared without exposing resident identity and revoked', function () {
    CensusReportGenerator::fake();
    $resident = Resident::factory()->create(['nickname' => '不应公开', 'phone' => '13800138000']);
    $matter = reportCensus($resident);
    Sanctum::actingAs($resident);

    $this->postJson("/api/matters/{$matter->id}/census-report")->assertSuccessful();
    $share = $this->postJson("/api/matters/{$matter->id}/census-report/share")
        ->assertSuccessful()
        ->assertJsonPath('share_enabled', true);

    $token = $share->json('share_token');
    $public = $this->getJson("/api/census-reports/{$token}")
        ->assertSuccessful()
        ->assertJsonMissing(['不应公开'])
        ->assertJsonMissing(['13800138000']);

    expect($public->json('report'))->toBeArray();

    $this->deleteJson("/api/matters/{$matter->id}/census-report/share")->assertSuccessful();
    $this->getJson("/api/census-reports/{$token}")->assertNotFound();
});

test('a resident cannot read another residents report', function () {
    $owner = Resident::factory()->create();
    $matter = reportCensus($owner);
    Sanctum::actingAs(Resident::factory()->create());

    $this->getJson("/api/matters/{$matter->id}/census-report")->assertNotFound();
});
