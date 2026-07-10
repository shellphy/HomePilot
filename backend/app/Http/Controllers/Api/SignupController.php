<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesResident;
use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SignupController extends Controller
{
    use ResolvesResident;

    /**
     * 报名接龙（幂等：重复报名不会产生第二条）。
     */
    public function store(Request $request, Project $project): JsonResponse
    {
        $resident = $this->resident($request);

        abort_if($resident->isMerchant(), 403, '商家账号不能报名接龙');

        $project->signups()->firstOrCreate(['resident_id' => $resident->id]);

        return response()->json([
            'joined' => true,
            'signups_count' => $project->signups()->count(),
        ], 201);
    }

    /**
     * 取消报名。
     */
    public function destroy(Request $request, Project $project): JsonResponse
    {
        $project->signups()->whereBelongsTo($this->resident($request), 'resident')->delete();

        return response()->json([
            'joined' => false,
            'signups_count' => $project->signups()->count(),
        ]);
    }
}
