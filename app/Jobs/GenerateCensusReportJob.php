<?php

namespace App\Jobs;

use App\Actions\GenerateCensusReport;
use App\Models\Stance;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateCensusReportJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    // 须 < 队列 retry_after，避免超时重复处理
    public int $timeout = 300;

    /** @var list<int> */
    public array $backoff = [15, 60];

    public int $uniqueFor = 600;

    public function __construct(public int $stanceId, public string $answerHash) {}

    public function uniqueId(): string
    {
        return "{$this->stanceId}:{$this->answerHash}";
    }

    public function handle(GenerateCensusReport $generate): void
    {
        $stance = Stance::query()->with(['matter', 'resident'])->find($this->stanceId);
        if ($stance === null || $stance->matter === null) {
            return;
        }

        $generate->handle($stance->matter, $stance, $stance->resident, $this->answerHash);
    }

    public function failed(?Throwable $exception): void
    {
        $stance = Stance::find($this->stanceId);
        if ($stance === null || ($stance->payload['ai_report_pending_hash'] ?? null) !== $this->answerHash) {
            return;
        }

        $payload = $stance->payload ?? [];
        $payload['ai_report_status'] = 'failed';
        $payload['ai_report_failed_hash'] = $this->answerHash;
        $payload['ai_report_error'] = 'AI 报告生成失败，请稍后重试';
        unset($payload['ai_report_pending_hash']);
        $stance->update(['payload' => $payload]);

        Log::error('Census report generation failed', [
            'stance_id' => $this->stanceId,
            'answer_hash' => $this->answerHash,
            'error' => $exception?->getMessage(),
        ]);
    }
}
