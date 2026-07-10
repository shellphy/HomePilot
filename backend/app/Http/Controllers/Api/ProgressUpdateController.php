<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesResident;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProgressUpdateRequest;
use App\Http\Resources\ProgressUpdateResource;
use App\Models\Project;
use Illuminate\Http\JsonResponse;

class ProgressUpdateController extends Controller
{
    use ResolvesResident;

    /**
     * 发进度：只有该项目的发起人可以。
     */
    public function store(StoreProgressUpdateRequest $request, Project $project): JsonResponse
    {
        abort_unless($project->initiator_id === $this->resident($request)->id, 403, '只有发起人可以操作');

        $update = $project->progressUpdates()->create($request->validated());

        return response()->json(['data' => ProgressUpdateResource::make($update)], 201);
    }
}
