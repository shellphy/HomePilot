<?php

namespace App\Actions;

use App\Ai\Agents\CensusReportGenerator;
use App\Matters\CensusReportPresentation;
use App\Models\Matter;
use App\Models\Resident;
use App\Models\Stance;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\StructuredAgentResponse;
use RuntimeException;

class GenerateCensusReport
{
    public function __construct(private CensusReportPresentation $presentation) {}

    /** @return array<string, mixed> */
    public function handle(Matter $matter, Stance $stance, Resident $resident, ?string $expectedHash = null): array
    {
        $answers = $stance->payload['answers'] ?? [];
        $answerHash = $this->answerHash($answers);
        $existing = $stance->payload['ai_report'] ?? null;

        if ($expectedHash !== null && $expectedHash !== $answerHash) {
            return is_array($existing) ? $existing : [];
        }

        if (is_array($existing) && ($stance->payload['ai_report_answers_hash'] ?? '') === $answerHash) {
            return $existing;
        }

        $prompt = json_encode([
            'questionnaire' => [
                'title' => $matter->title,
                'purpose' => $matter->payloadValue('purpose', ''),
                'report_presentation' => $this->presentation->for($matter),
                'modules' => $matter->payloadList('modules'),
            ],
            'resident_context' => [
                'layout' => $resident->layout_label,
            ],
            'answers' => $answers,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);

        $response = (new CensusReportGenerator)->prompt($prompt);
        if (! $response instanceof StructuredAgentResponse) {
            throw new RuntimeException('AI 未返回结构化问卷总结');
        }
        $report = $response->toArray();

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
        $payload['report_share_token'] ??= Str::random(48);
        $payload['report_share_enabled'] ??= false;
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
