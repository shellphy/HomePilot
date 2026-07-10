<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Resident;
use App\Settings\CommunitySettings;
use Illuminate\Http\JsonResponse;

class StatsController extends Controller
{
    /**
     * 小区概况：总户数与入驻成员数。
     * 各类主题数据（装修意向等）的聚合在对应征集事务的 /matters/{id}/census 里。
     */
    public function index(CommunitySettings $settings): JsonResponse
    {
        return response()->json([
            'residents' => Resident::count(),
            'total_households' => $settings->total_households,
        ]);
    }
}
