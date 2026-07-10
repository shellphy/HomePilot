<?php

namespace App\Http\Controllers\Api;

use App\Enums\ProjectStatus;
use App\Http\Controllers\Api\Concerns\ResolvesResident;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProjectController extends Controller
{
    use ResolvesResident;

    /**
     * 团购列表（仅已审核）：接龙中 > 谈判中 > 意向征集，已成团垫底。
     */
    public function index(): AnonymousResourceCollection
    {
        $displayOrder = [
            ProjectStatus::Open->value => 0,
            ProjectStatus::Negotiating->value => 1,
            ProjectStatus::Seeking->value => 2,
            ProjectStatus::Done->value => 3,
        ];

        $projects = Project::approved()
            ->withCount('signups')
            ->latest()
            ->get()
            ->sortBy(fn (Project $project): int => $displayOrder[$project->status->value])
            ->values();

        return ProjectResource::collection($projects);
    }

    /**
     * 我发起的团购（含待审核的）。
     */
    public function mine(Request $request): AnonymousResourceCollection
    {
        $projects = Project::whereBelongsTo($this->resident($request), 'initiator')
            ->withCount('signups')
            ->latest()
            ->get();

        return ProjectResource::collection($projects);
    }

    /**
     * 详情：未审核的项目只有发起人自己能看。
     */
    public function show(Request $request, Project $project): ProjectResource
    {
        $resident = $this->resident($request);

        abort_unless($project->is_approved || $project->initiator_id === $resident->id, 404);

        $project
            ->loadCount('signups')
            ->load([
                'initiator',
                'signups' => fn ($query) => $query->with('resident')->oldest(),
                'progressUpdates' => fn ($query) => $query->latest('happened_on'),
            ]);

        return ProjectResource::make($project)
            ->additional(['joined' => $project->signups->contains('resident_id', $resident->id)]);
    }

    /**
     * 任何业主都可以发起团购，发起人即该项目团长；管理员审核后才对外展示。
     */
    public function store(StoreProjectRequest $request): JsonResponse
    {
        $resident = $this->resident($request);

        abort_if($resident->isMerchant(), 403, '商家账号不能发起团购');

        $project = Project::create([
            ...$request->payload(),
            'initiator_id' => $resident->id,
            'is_approved' => false,
        ]);

        return response()->json(['data' => ProjectResource::make($project)], 201);
    }

    /**
     * 只有发起人本人可以编辑/流转状态。
     */
    public function update(StoreProjectRequest $request, Project $project): JsonResponse
    {
        abort_unless($project->initiator_id === $this->resident($request)->id, 403, '只有发起人可以操作');

        $project->update($request->payload());

        return response()->json(['data' => ProjectResource::make($project)]);
    }
}
