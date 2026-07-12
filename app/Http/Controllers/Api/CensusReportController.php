<?php

namespace App\Http\Controllers\Api;

use App\Actions\GenerateCensusReport;
use App\Http\Controllers\Api\Concerns\ResolvesResident;
use App\Http\Controllers\Controller;
use App\Models\Matter;
use App\Models\Stance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CensusReportController extends Controller
{
    use ResolvesResident;

    public function show(Request $request, Matter $matter): JsonResponse
    {
        $stance = $this->ownedStance($request, $matter);

        return response()->json($this->responseData($matter, $stance));
    }

    public function store(Request $request, Matter $matter, GenerateCensusReport $generate): JsonResponse
    {
        $resident = $this->resident($request);
        $stance = $this->ownedStance($request, $matter);
        $generate->handle($matter, $stance, $resident);

        return response()->json($this->responseData($matter, $stance->refresh()));
    }

    public function share(Request $request, Matter $matter): JsonResponse
    {
        $stance = $this->ownedStance($request, $matter);
        abort_unless(is_array($stance->payload['ai_report'] ?? null), 422, '请先生成需求报告');
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
        $report = $stance->payload['ai_report'] ?? null;
        $shareEnabled = (bool) ($stance->payload['report_share_enabled'] ?? false);

        return [
            'title' => $matter->title,
            'report' => is_array($report) ? $report : null,
            'generated_at' => $stance->payload['ai_report_generated_at'] ?? null,
            'share_enabled' => $shareEnabled,
            'share_token' => $shareEnabled ? ($stance->payload['report_share_token'] ?? null) : null,
        ];
    }
}
