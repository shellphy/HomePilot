<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesResident;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRegistrationRequest;
use App\Http\Resources\RegistrationResource;
use App\Models\Registration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegistrationController extends Controller
{
    use ResolvesResident;

    /**
     * 当前业主自己的登记（未登记时返回 null data）。
     */
    public function show(Request $request): JsonResponse
    {
        $registration = $this->resident($request)->registration;

        return response()->json([
            'data' => $registration ? RegistrationResource::make($registration) : null,
        ]);
    }

    /**
     * 提交/更新意向登记（一户一份，重复提交视为更新）。
     */
    public function store(StoreRegistrationRequest $request): JsonResponse
    {
        $resident = $this->resident($request);

        abort_if($resident->isMerchant(), 403, '商家账号不能填写业主登记');

        $resident->update([
            'wechat_id' => $request->validated('wechat_id'),
            'phone' => $request->validated('phone') ?? $resident->phone,
        ]);

        $registration = Registration::updateOrCreate(
            ['resident_id' => $resident->id],
            $request->safe()->except(['phone', 'wechat_id']),
        );

        return response()->json([
            'data' => RegistrationResource::make($registration),
        ], $registration->wasRecentlyCreated ? 201 : 200);
    }
}
