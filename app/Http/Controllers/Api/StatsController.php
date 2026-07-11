<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Matters\CensusType;
use App\Models\Matter;
use App\Models\Resident;
use App\Models\Stance;
use App\Settings\CommunitySettings;
use Illuminate\Http\JsonResponse;

class StatsController extends Controller
{
    /**
     * 小区概况：总户数、入驻成员数、各品类的团购意向户数。
     * 各类主题数据（装修意向等）的完整聚合在对应征集事项的 /matters/{id}/census 里。
     */
    public function index(CommunitySettings $settings): JsonResponse
    {
        return response()->json([
            'residents' => Resident::count(),
            'total_households' => $settings->total_households,
            'category_interest' => $this->categoryInterest(),
        ]);
    }

    /**
     * 品类 => 意向人数：来自征集问卷里 key 为约定值的多选题（见 CensusType）。
     * 发起团购页据此在品类旁展示"已有 N 人想团"。
     *
     * @return array<string, int>
     */
    private function categoryInterest(): array
    {
        return Matter::approved()
            ->where('type', 'census')
            ->with(['stances' => fn ($query) => $query->where('mode', Stance::MODE_REGISTER)])
            ->get()
            ->flatMap(fn (Matter $matter) => $matter->stances)
            ->flatMap(function (Stance $stance): array {
                $interests = $stance->payload['answers'][CensusType::CATEGORY_INTEREST_KEY] ?? [];

                return is_array($interests) ? $interests : [];
            })
            ->countBy()
            ->all();
    }
}
