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
     * 发布事项时间线（进度）：只有发起人可以。
     */
    public function store(Request $request, Matter $matter): JsonResponse
    {
        abort_unless($matter->initiator_id === $this->resident($request)->id, 403, '只有发起人可以操作');

        $validated = $request->validate([
            'happened_on' => ['required', 'date'],
            'content' => ['required', 'string', 'max:500'],
            'images' => ['nullable', 'array', 'max:9'],
            'images.*' => ['url'],
        ], [
            'content.required' => '写一句进度内容吧',
        ]);

        $update = $matter->updates()->create($validated);

        MatterUpdatePosted::dispatch($update);

        return response()->json(['data' => MatterUpdateResource::make($update)], 201);
    }
}
