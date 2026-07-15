<?php

namespace App\Jobs;

use App\Models\Stance;
use App\Services\GenerateCensusReport;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateCensusReportJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    // 联网检索拉长生成时间；超时链须满足 agent(240) < job < 队列 retry_after(360)
    public int $timeout = 300;

    /** @var list<int> */
    public array $backoff = [15, 60];

    public int $uniqueFor = 600;

    public function __construct(public int $stanceId, public string $answerHash, public bool $force = false) {}

    public function uniqueId(): string
    {
        return "{$this->stanceId}:{$this->answerHash}";
    }

    public function handle(GenerateCensusReport $generate): void
    {
        $stance = Stance::query()->with(['matter', 'resident'])->find($this->stanceId);
        if ($stance === null || $stance->matter === null) {
            Log::warning('AI 问卷报告任务跳过：表态或事项已不存在', ['stance_id' => $this->stanceId]);

            return;
        }

        $generate->handle($stance->matter, $stance, $stance->resident, $this->answerHash, $this->force);
    }

    public function failed(?Throwable $exception): void
    {
        // 记在前面：答案已变更时下面会提前返回
        Log::error('AI 问卷报告生成失败', [
            'stance_id' => $this->stanceId,
            'answer_hash' => $this->answerHash,
            'error' => $exception?->getMessage(),
        ]);

        $stance = Stance::find($this->stanceId);

        // 答案已变更：状态交给更新的那次任务写
        if ($stance === null || ($stance->payload['ai_report_pending_hash'] ?? null) !== $this->answerHash) {
            return;
        }

        $payload = $stance->payload ?? [];
        $payload['ai_report_status'] = 'failed';
        $payload['ai_report_failed_hash'] = $this->answerHash;
        $payload['ai_report_error'] = 'AI 报告生成失败，请稍后重试';
        unset($payload['ai_report_pending_hash']);
        $stance->update(['payload' => $payload]);
    }
}
