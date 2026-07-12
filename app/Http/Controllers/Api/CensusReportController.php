<?php

namespace App\Http\Controllers\Api;

use App\Actions\GenerateCensusReport;
use App\Http\Controllers\Api\Concerns\ResolvesResident;
use App\Http\Controllers\Controller;
use App\Jobs\GenerateCensusReportJob;
use App\Matters\CensusReportPresentation;
use App\Models\Matter;
use App\Models\Stance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CensusReportController extends Controller
{
    use ResolvesResident;

    public function __construct(private CensusReportPresentation $presentation) {}

    public function show(Request $request, Matter $matter): JsonResponse
    {
        $stance = $this->ownedStance($request, $matter);

        return response()->json($this->responseData($matter, $stance));
    }

    public function store(Request $request, Matter $matter, GenerateCensusReport $generate): JsonResponse
    {
        $stance = $this->ownedStance($request, $matter);
        $answers = $stance->payload['answers'] ?? [];
        $answerHash = $generate->answerHash(is_array($answers) ? $answers : []);
        $payload = $stance->payload ?? [];

        if (is_array($payload['ai_report'] ?? null)
            && ($payload['ai_report_answers_hash'] ?? null) === $answerHash) {
            return response()->json($this->responseData($matter, $stance));
        }

        if (($payload['ai_report_pending_hash'] ?? null) !== $answerHash) {
            $payload['ai_report_status'] = 'pending';
            $payload['ai_report_pending_hash'] = $answerHash;
            $payload['ai_report_requested_at'] = now()->toIso8601String();
            unset($payload['ai_report_failed_hash'], $payload['ai_report_error']);
            $stance->update(['payload' => $payload]);

            GenerateCensusReportJob::dispatch($stance->id, $answerHash);
        }

        return response()->json($this->responseData($matter, $stance->refresh()), 202);
    }

    public function share(Request $request, Matter $matter): JsonResponse
    {
        $stance = $this->ownedStance($request, $matter);
        abort_unless(is_array($stance->payload['ai_report'] ?? null), 422, '请先生成问卷总结');
        $payload = $stance->payload;
        $payload['report_share_enabled'] = true;
        $stance->update(['payload' => $payload]);

        return response()->json($this->responseData($matter, $stance->refresh()));
    }

    public function revoke(Request $request, Matter $matter): JsonResponse
    {
        $stance = $this->ownedStance($request, $matter);
        $payload = $stance->payload;
        $payload['report_share_enabled'] = false;
        $payload['report_share_token'] = Str::random(48);
        $stance->update(['payload' => $payload]);

        return response()->json($this->responseData($matter, $stance->refresh()));
    }

    public function shared(string $token): JsonResponse
    {
        $stance = Stance::query()
            ->where('mode', Stance::MODE_REGISTER)
            ->where('payload->report_share_token', $token)
            ->where('payload->report_share_enabled', true)
            ->with('matter')
            ->firstOrFail();

        return response()->json([
            'title' => $stance->matter->title,
            'report' => $stance->payload['ai_report'],
            'presentation' => $this->presentation->for($stance->matter),
            'generated_at' => $stance->payload['ai_report_generated_at'] ?? null,
            'shared' => true,
        ]);
    }

    private function ownedStance(Request $request, Matter $matter): Stance
    {
        abort_unless($matter->type === 'census', 404);

        return $matter->stances()
            ->where('mode', Stance::MODE_REGISTER)
            ->where('resident_id', $this->resident($request)->id)
            ->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function responseData(Matter $matter, Stance $stance): array
    {
        $payload = $stance->payload ?? [];
        $answers = $payload['answers'] ?? [];
        $answerHash = hash('sha256', json_encode(is_array($answers) ? $answers : [], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $report = $payload['ai_report'] ?? null;
        $reportIsCurrent = is_array($report) && ($payload['ai_report_answers_hash'] ?? null) === $answerHash;
        $status = match (true) {
            $reportIsCurrent => 'completed',
            ($payload['ai_report_pending_hash'] ?? null) === $answerHash => 'pending',
            ($payload['ai_report_failed_hash'] ?? null) === $answerHash => 'failed',
            default => 'idle',
        };
        $shareEnabled = (bool) ($stance->payload['report_share_enabled'] ?? false);

        return [
            'title' => $matter->title,
            'report' => $reportIsCurrent ? $report : null,
            'generation_status' => $status,
            'generation_error' => $status === 'failed' ? ($payload['ai_report_error'] ?? 'AI 报告生成失败，请稍后重试') : null,
            'presentation' => $this->presentation->for($matter),
            'generated_at' => $stance->payload['ai_report_generated_at'] ?? null,
            'share_enabled' => $shareEnabled,
            'share_token' => $shareEnabled ? ($stance->payload['report_share_token'] ?? null) : null,
        ];
    }
}
