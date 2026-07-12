<?php

namespace App\Actions;

use App\Ai\Agents\CensusReportGenerator;
use App\Models\Matter;
use App\Models\Resident;
use App\Models\Stance;
use Illuminate\Support\Str;

class GenerateCensusReport
{
    /** @return array<string, mixed> */
    public function handle(Matter $matter, Stance $stance, Resident $resident): array
    {
        $answers = $stance->payload['answers'] ?? [];
        $answerHash = hash('sha256', json_encode($answers, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $existing = $stance->payload['ai_report'] ?? null;

        if (is_array($existing) && ($stance->payload['ai_report_answers_hash'] ?? '') === $answerHash) {
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

        $report = (new CensusReportGenerator)->prompt($prompt)->toArray();
        $payload = $stance->payload ?? [];
        $payload['ai_report'] = $report;
        $payload['ai_report_answers_hash'] = $answerHash;
        $payload['ai_report_generated_at'] = now()->toIso8601String();
        $payload['report_share_token'] ??= Str::random(48);
        $payload['report_share_enabled'] ??= false;
        $stance->update(['payload' => $payload]);

        return $report;
    }
}
