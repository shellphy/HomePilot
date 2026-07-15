<?php

namespace App\Services;

use App\Ai\Agents\CensusReportGenerator;
use App\Models\Matter;
use App\Models\Resident;
use App\Models\Stance;
use Illuminate\Support\Facades\Log;

class GenerateCensusReport
{
    public function handle(Matter $matter, Stance $stance, Resident $resident, ?string $expectedHash = null, bool $force = false): void
    {
        $answers = $stance->payload['answers'] ?? [];
        $answerHash = $this->answerHash($answers);
        $existing = $stance->payload['ai_report'] ?? null;

        if ($expectedHash !== null && $expectedHash !== $answerHash) {
            Log::debug('AI 问卷报告跳过：答案已变更', ['stance_id' => $stance->id]);

            return;
        }

        // 强制重生成跳过「已有相同答案的报告就不跑」的短路
        if (! $force && is_string($existing) && $existing !== '' && ($stance->payload['ai_report_answers_hash'] ?? '') === $answerHash) {
            Log::debug('AI 问卷报告跳过：已有同答案的报告', ['stance_id' => $stance->id]);

            return;
        }

        $prompt = json_encode([
            'questionnaire' => [
                'title' => $matter->title,
                'purpose' => $matter->payloadValue('purpose', ''),
                'modules' => $matter->payloadList('modules'),
            ],
            'resident_context' => [
                'layout' => $resident->layout_label,
            ],
            'answers' => $answers,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);

        Log::info('AI 问卷报告生成开始', [
            'matter_id' => $matter->id,
            'stance_id' => $stance->id,
            'resident_id' => $resident->id,
        ]);

        $report = trim((new CensusReportGenerator)->prompt($prompt)->text);

        Log::debug('AI 问卷报告生成完成', [
            'matter_id' => $matter->id,
            'stance_id' => $stance->id,
            'length' => mb_strlen($report),
        ]);

        // 生成期间答案又变了：丢弃这次结果，别覆盖更新的答案
        $stance->refresh();
        $latestAnswers = $stance->payload['answers'] ?? [];
        if ($expectedHash !== null
            && ($this->answerHash($latestAnswers) !== $expectedHash
                || ($stance->payload['ai_report_pending_hash'] ?? null) !== $expectedHash)) {
            Log::info('AI 问卷报告生成后被丢弃：答案在生成期间变了', [
                'matter_id' => $matter->id,
                'stance_id' => $stance->id,
            ]);

            return;
        }

        $payload = $stance->payload ?? [];
        $payload['ai_report'] = $report;
        $payload['ai_report_answers_hash'] = $answerHash;
        $payload['ai_report_generated_at'] = now()->toIso8601String();
        $payload['ai_report_status'] = 'completed';
        unset(
            $payload['ai_report_pending_hash'],
            $payload['ai_report_failed_hash'],
            $payload['ai_report_error'],
        );
        $stance->update(['payload' => $payload]);
    }

    /** @param array<string, mixed> $answers */
    public function answerHash(array $answers): string
    {
        return hash('sha256', json_encode($answers, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
