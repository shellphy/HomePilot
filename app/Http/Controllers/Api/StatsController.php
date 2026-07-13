<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Matter;
use App\Models\Party;
use App\Models\Resident;
use Illuminate\Http\JsonResponse;

class StatsController extends Controller
{
    /**
     * 小区概况：入驻成员数、已核验相关方数、正在征集数。
     * 各类主题数据（装修意向等）的完整聚合在对应征集事项的 /matters/{id}/census 里。
     */
    public function index(): JsonResponse
    {
        return response()->json([
            // 只算选好楼栋的成员：静默登录会把路过的人也建号，不选楼栋不算「邻居」
            'residents' => Resident::where('unit_label', '!=', '')->count(),
            // 喂给首页「已核验商家名录」入口：只算商家（治理身份不进名录）
            'listed_parties' => Party::listed()->where('type', Party::TYPE_MERCHANT)->count(),
            // 喂给首页「正在征集」指引卡：进行中的征集数，点进「数据」tab
            'open_census_count' => Matter::approved()->where('type', 'census')->where('state', 'open')->count(),
        ]);
    }
}
