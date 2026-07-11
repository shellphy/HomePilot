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
        return ResidentResource::make(
            $this->resident($request)->load('affiliatedParty'),
        );
    }

    public function update(UpdateProfileRequest $request): ResidentResource
    {
        $resident = $this->resident($request);
        $validated = $request->validated();

        // 楼栋号是自报标签，规整掉首尾空格
        if (array_key_exists('unit_label', $validated)) {
            $validated['unit_label'] = trim((string) ($validated['unit_label'] ?? ''));
        }

        // 字符串字段：空提交规整为空串（清空生效）
        $resident->fill(collect($validated)->map(fn ($value) => $value ?? '')->all());
        $resident->save();

        // 与 show() 保持一致的完整视图，小程序端会直接把该响应当缓存用
        return ResidentResource::make($resident->load('affiliatedParty'));
    }
}
