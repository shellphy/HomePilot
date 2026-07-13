<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesResident;
use App\Http\Controllers\Controller;
use App\Models\Matter;
use App\Models\Stance;
use App\Services\CensusAggregator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CensusOverviewController extends Controller
{
    use ResolvesResident;

    public function __construct(private CensusAggregator $aggregator) {}

    public function __invoke(Request $request): JsonResponse
    {
        $resident = $this->resident($request);
        $matters = Matter::approved()
            ->where('type', 'census')
            ->with(['stances' => fn ($query) => $query->where('mode', Stance::MODE_REGISTER)])
            ->latest()
            ->get()
            ->map(function (Matter $matter) use ($resident): array {
                $myStance = $matter->stances->firstWhere('resident_id', $resident->id);
                $registered = $matter->stances->count();
                $aggregatesVisible = $registered >= CensusAggregator::MINIMUM_PUBLIC_RESPONSES
                    && ($myStance !== null || $matter->state !== 'open');
                $aggregates = $aggregatesVisible ? $this->aggregator->for($matter) : [];

                return [
                    'id' => $matter->id,
                    'title' => $matter->title,
                    'state' => $matter->state,
                    'state_label' => $matter->state === 'open' ? '征集中' : '已结束',
                    'pitch' => $matter->payloadValue('pitch', ''),
                    'registered' => $registered,
                    'my_answered' => count($myStance?->payload['answers'] ?? []),
                    'aggregates_visible' => $aggregatesVisible,
                    'top' => $this->topAnswer($aggregates),
                ];
            });

        return response()->json(['data' => $matters]);
    }

    /** @param array<int, array<string, mixed>> $aggregates */
    private function topAnswer(array $aggregates): ?array
    {
        $question = collect($aggregates)
            ->flatMap(fn (array $module): array => $module['questions'] ?? [])
            ->first(fn (array $item): bool => collect($item['counts'] ?? [])->isNotEmpty());

        if ($question === null) {
            return null;
        }

        $counts = collect($question['counts']);

        return [
            'question' => $question['text'],
            'label' => (string) $counts->keys()->first(),
            'count' => (int) $counts->first(),
        ];
    }
}
