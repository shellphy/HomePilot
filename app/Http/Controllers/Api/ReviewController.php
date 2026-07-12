<?php

namespace App\Http\Controllers\Api;

use App\Events\MatterReviewed;
use App\Http\Controllers\Api\Concerns\ResolvesResident;
use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Matters\MatterTypeRegistry;
use App\Models\Matter;
use App\Models\Stance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    use ResolvesResident;

    /**
     * 评价表态（一人一评，重复提交视为修改并留修订链）。
     */
    public function store(Request $request, Matter $matter): JsonResponse
    {
        $resident = $this->resident($request);
        $type = MatterTypeRegistry::for($matter->type);

        abort_unless($type->reviewOpen($matter), 422, '还没到评价阶段');
        abort_unless($type->isParticipant($matter, $resident), 403, '只有参与的业主可以评价');

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'content' => ['sometimes', 'nullable', 'string', 'max:500'],
        ], [
            'rating.required' => '请先打个分',
        ]);

        $payload = ['rating' => $validated['rating'], 'content' => $validated['content'] ?? ''];

        $review = $matter->reviews()->where('resident_id', $resident->id)->first();

        if ($review) {
            $review->reviseTo($payload);
        } else {
            $review = $matter->reviews()->create([
                'resident_id' => $resident->id,
                'mode' => Stance::MODE_REVIEW,
                'payload' => $payload,
            ]);

            MatterReviewed::dispatch($matter, $review);
        }

        return response()->json(
            ['data' => ReviewResource::make($review->load('resident'))],
            $review->wasRecentlyCreated ? 201 : 200,
        );
    }
}
