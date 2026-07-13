<?php

namespace App\Actions;

use App\Ai\Agents\CensusReportGenerator;
use App\Models\Matter;
use App\Models\Resident;
use App\Models\Stance;

class GenerateCensusReport
{
    public function handle(Matter $matter, Stance $stance, Resident $resident, ?string $expectedHash = null): string
    {
        $answers = $stance->payload['answers'] ?? [];
        $answerHash = $this->answerHash($answers);
        $existing = $stance->payload['ai_report'] ?? null;

        if ($expectedHash !== null && $expectedHash !== $answerHash) {
            return is_string($existing) ? $existing : '';
        }

        if (is_string($existing) && $existing !== '' && ($stance->payload['ai_report_answers_hash'] ?? '') === $answerHash) {
            return $existing;
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

        $report = trim((new CensusReportGenerator)->prompt($prompt)->text);

        $stance->refresh();
        $latestAnswers = $stance->payload['answers'] ?? [];
        if (($expectedHash !== null && $this->answerHash($latestAnswers) !== $expectedHash)
            || ($expectedHash !== null && ($stance->payload['ai_report_pending_hash'] ?? null) !== $expectedHash)) {
            return $report;
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

        return $report;
    }

    /** @param array<string, mixed> $answers */
    public function answerHash(array $answers): string
    {
        return hash('sha256', json_encode($answers, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
