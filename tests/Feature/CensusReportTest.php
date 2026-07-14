<?php

use App\Ai\Agents\CensusReportGenerator;
use App\Jobs\GenerateCensusReportJob;
use App\Models\Matter;
use App\Models\Resident;
use App\Models\Stance;
use App\Services\GenerateCensusReport;
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

test('report agent does not assume questionnaire content is correct', function () {
    expect((string) (new CensusReportGenerator)->instructions())
        ->toContain('不要顺着明显错误继续推导');
});

test('a resident generates and reuses a markdown census report', function () {
    Queue::fake();
    CensusReportGenerator::fake(["## 我的问卷总结\n\n- 重视环保与防潮"]);
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
        ->assertJsonStructure(['report', 'presentation' => ['empty_description']]);
    expect($first->json('report'))->toBeString()->toContain('重视环保与防潮');

    $this->postJson("/api/matters/{$matter->id}/census-report")
        ->assertSuccessful()
        ->assertJsonPath('report', $first->json('report'));

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

test('强制重新生成即便答案没变也会重跑', function () {
    Queue::fake();
    $resident = Resident::factory()->create();
    $matter = reportCensus($resident);
    $stance = $matter->stances()->where('mode', Stance::MODE_REGISTER)->sole();
    $hash = app(GenerateCensusReport::class)->answerHash($stance->payload['answers']);
    // 预置一份和当前答案匹配的已完成报告
    $stance->update(['payload' => array_merge($stance->payload, [
        'ai_report' => '## 旧总结',
        'ai_report_answers_hash' => $hash,
        'ai_report_status' => 'completed',
    ])]);
    Sanctum::actingAs($resident);

    // 普通请求答案没变就复用旧报告，不入队
    $this->postJson("/api/matters/{$matter->id}/census-report")
        ->assertSuccessful()
        ->assertJsonPath('report', '## 旧总结');
    Queue::assertNotPushed(GenerateCensusReportJob::class);

    // force=true 跳过复用：重新入队、回到 pending 并隐藏旧报告
    $this->postJson("/api/matters/{$matter->id}/census-report", ['force' => true])
        ->assertAccepted()
        ->assertJsonPath('generation_status', 'pending')
        ->assertJsonPath('report', null);
    Queue::assertPushed(GenerateCensusReportJob::class, fn (GenerateCensusReportJob $job): bool => $job->force === true);

    // 跑掉强制任务：即便答案没变也覆盖出新报告
    CensusReportGenerator::fake(['## 新总结']);
    $forced = null;
    Queue::assertPushed(GenerateCensusReportJob::class, function (GenerateCensusReportJob $job) use (&$forced): bool {
        $forced = $job;

        return true;
    });
    $forced->handle(app(GenerateCensusReport::class));

    $this->getJson("/api/matters/{$matter->id}/census-report")
        ->assertJsonPath('generation_status', 'completed')
        ->assertJsonPath('report', '## 新总结');
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

    // 联网检索拉长生成时间：agent HTTP 超时 < job 超时 < 队列 retry_after，逐层留富余
    expect($agentTimeout)->toBe(240)
        ->and($job->timeout)->toBe(300)
        ->and(config('queue.connections.database.retry_after'))->toBe(360)
        ->and($agentTimeout)->toBeLessThan($job->timeout)
        ->and($job->timeout)->toBeLessThan(config('queue.connections.database.retry_after'));
});
