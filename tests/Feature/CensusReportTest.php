<?php

use App\Actions\GenerateCensusReport;
use App\Ai\Agents\CensusReportGenerator;
use App\Jobs\GenerateCensusReportJob;
use App\Models\Matter;
use App\Models\Resident;
use App\Models\Stance;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Attributes\Timeout as AiTimeout;
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

function runQueuedCensusReport(): void
{
    $queuedJob = null;
    Queue::assertPushed(GenerateCensusReportJob::class, function (GenerateCensusReportJob $job) use (&$queuedJob): bool {
        $queuedJob = $job;

        return true;
    });

    expect($queuedJob)->toBeInstanceOf(GenerateCensusReportJob::class);
    $queuedJob->handle(app(GenerateCensusReport::class));
}

test('a resident generates and reuses a structured census report', function () {
    Queue::fake();
    CensusReportGenerator::fake();
    $resident = Resident::factory()->create(['layout_label' => '130㎡']);
    $matter = reportCensus($resident);
    Sanctum::actingAs($resident);

    $this->postJson("/api/matters/{$matter->id}/census-report")
        ->assertAccepted()
        ->assertJsonPath('generation_status', 'pending')
        ->assertJsonPath('report', null);

    $this->postJson("/api/matters/{$matter->id}/census-report")
        ->assertAccepted()
        ->assertJsonPath('generation_status', 'pending');
    Queue::assertPushedTimes(GenerateCensusReportJob::class, 1);

    runQueuedCensusReport();

    $first = $this->getJson("/api/matters/{$matter->id}/census-report")
        ->assertSuccessful()
        ->assertJsonPath('generation_status', 'completed')
        ->assertJsonStructure([
            'report' => ['headline', 'overview', 'priorities', 'decisions', 'open_questions', 'risks'],
            'presentation' => ['profile_label', 'report_title', 'risk_label'],
        ])
        ->assertJsonPath('presentation.report_title', '我的问卷总结');

    $this->postJson("/api/matters/{$matter->id}/census-report")
        ->assertSuccessful()
        ->assertJsonPath('report.headline', $first->json('report.headline'));

    Queue::assertPushedTimes(GenerateCensusReportJob::class, 1);
    CensusReportGenerator::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('130㎡') && $prompt->contains('老人'));
});

test('a failed report job exposes a retryable status', function () {
    Queue::fake();
    $resident = Resident::factory()->create();
    $matter = reportCensus($resident);
    Sanctum::actingAs($resident);

    $this->postJson("/api/matters/{$matter->id}/census-report")->assertAccepted();

    $queuedJob = null;
    Queue::assertPushed(GenerateCensusReportJob::class, function (GenerateCensusReportJob $job) use (&$queuedJob): bool {
        $queuedJob = $job;

        return true;
    });
    $queuedJob->failed(new RuntimeException('provider unavailable'));

    $this->getJson("/api/matters/{$matter->id}/census-report")
        ->assertSuccessful()
        ->assertJsonPath('generation_status', 'failed')
        ->assertJsonPath('generation_error', 'AI 报告生成失败，请稍后重试');
});

test('an outdated report job does not overwrite newer answers', function () {
    Queue::fake();
    CensusReportGenerator::fake();
    $resident = Resident::factory()->create();
    $matter = reportCensus($resident);
    Sanctum::actingAs($resident);

    $this->postJson("/api/matters/{$matter->id}/census-report")->assertAccepted();

    $queuedJob = null;
    Queue::assertPushed(GenerateCensusReportJob::class, function (GenerateCensusReportJob $job) use (&$queuedJob): bool {
        $queuedJob = $job;

        return true;
    });

    $stance = $matter->stances()->where('resident_id', $resident->id)->firstOrFail();
    $payload = $stance->payload;
    $payload['answers'] = ['elderly' => '没有'];
    $stance->update(['payload' => $payload]);

    $queuedJob->handle(app(GenerateCensusReport::class));

    $this->getJson("/api/matters/{$matter->id}/census-report")
        ->assertSuccessful()
        ->assertJsonPath('generation_status', 'idle')
        ->assertJsonPath('report', null);
});

test('a resident cannot read another residents report', function () {
    $owner = Resident::factory()->create();
    $matter = reportCensus($owner);
    Sanctum::actingAs(Resident::factory()->create());

    $this->getJson("/api/matters/{$matter->id}/census-report")->assertNotFound();
});

test('report generation timeouts allow slow structured responses to finish', function () {
    $agentTimeout = (new ReflectionClass(CensusReportGenerator::class))
        ->getAttributes(AiTimeout::class)[0]
        ->newInstance()
        ->value;
    $job = new GenerateCensusReportJob(1, 'answer-hash');

    // agent < job < 队列 retry_after，逐层留富余，报告实测一分多钟也不会被判超时/重复处理
    expect($agentTimeout)->toBe(240)
        ->and($job->timeout)->toBe(300)
        ->and(config('queue.connections.database.retry_after'))->toBe(360);
});
