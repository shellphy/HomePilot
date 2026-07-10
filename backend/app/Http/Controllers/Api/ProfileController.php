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
            $this->resident($request)->load(['unit', 'party']),
        );
    }

    public function update(UpdateProfileRequest $request): ResidentResource
    {
        $resident = $this->resident($request);
        $validated = $request->validated();

        // 楼栋号：传值绑定户，传空解绑（清空要生效）
        if (array_key_exists('unit_label', $validated)) {
            $label = trim((string) ($validated['unit_label'] ?? ''));
            if ($label === '') {
                $resident->unit_id = null;
            } else {
                $resident->bindUnit($label);
            }
            unset($validated['unit_label']);
        }

        // 其余字符串字段：空提交规整为空串（清空生效）
        $resident->fill(collect($validated)->map(fn ($value) => $value ?? '')->all());
        $resident->save();

        // 与 show() 保持一致的完整视图，小程序端会直接把该响应当缓存用
        return ResidentResource::make($resident->load(['unit', 'party']));
    }
}
