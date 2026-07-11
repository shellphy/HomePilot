<?php

namespace App\Http\Controllers\Api;

use App\Events\MatterUpdatePosted;
use App\Http\Controllers\Api\Concerns\ResolvesResident;
use App\Http\Controllers\Controller;
use App\Http\Resources\MatterUpdateResource;
use App\Models\Matter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MatterUpdateController extends Controller
{
    use ResolvesResident;

    /**
     * 发布事项时间线：发起人发进展；被认证的治理类相关方（物业/开发商/业委会）发官方回应。
     */
    public function store(Request $request, Matter $matter): JsonResponse
    {
        $resident = $this->resident($request);
        $isInitiator = $matter->initiator_id === $resident->id;
        $party = $resident->affiliatedParty;
        $isOfficial = $party !== null && $party->is_listed && $party->isGovernance();

        abort_unless($isInitiator || $isOfficial, 403, '只有发起人或被认证的相关方可以操作');

        $validated = $request->validate([
            'happened_on' => ['required', 'date'],
            'content' => ['required', 'string', 'max:500'],
            'images' => ['nullable', 'array', 'max:9'],
            'images.*' => ['url'],
        ], [
            'content.required' => '写一句进度内容吧',
        ]);

        // 发起人身份优先：牵头人恰好也是相关方成员时，发的是进展不是官方回应
        $update = $matter->updates()->create($validated + [
            'author_party_id' => $isInitiator ? null : $party->id,
        ]);

        $matter->recordActivity($resident);
        MatterUpdatePosted::dispatch($update);

        return response()->json(['data' => MatterUpdateResource::make($update)], 201);
    }
}
