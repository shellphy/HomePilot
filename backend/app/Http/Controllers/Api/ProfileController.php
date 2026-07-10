<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesResident;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\ResidentResource;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    use ResolvesResident;

    public function show(Request $request): ResidentResource
    {
        return ResidentResource::make($this->resident($request)->loadMissing('registration'));
    }

    public function update(UpdateProfileRequest $request): ResidentResource
    {
        $resident = $this->resident($request);
        $resident->update($request->validated());

        // 与 show() 保持一致地带上登记信息，小程序端会直接把该响应当缓存用
        return ResidentResource::make($resident->loadMissing('registration'));
    }
}
