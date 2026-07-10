<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Registration;
use Illuminate\Http\JsonResponse;

class StatsController extends Controller
{
    /**
     * 小区装修进度地图的数据源：登记总量 + 户型/开工时间/品类意向分布。
     */
    public function index(): JsonResponse
    {
        $registrations = Registration::all();

        return response()->json([
            'registered' => $registrations->count(),
            'total_households' => config('homepilot.total_households'),
            'layouts' => $registrations->countBy('layout')->sortDesc(),
            'decoration_modes' => $registrations->countBy('decoration_mode')->sortDesc(),
            'interests' => $registrations
                ->flatMap(fn (Registration $registration): array => $registration->interests)
                ->countBy()
                ->sortDesc(),
        ]);
    }
}
