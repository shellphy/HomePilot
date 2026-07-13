<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesResident;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\ResidentResource;
use App\Models\Matter;
use App\Services\WeChat;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

        // 字符串字段：空提交规整为空串（清空生效）
        $resident->fill(collect($request->validated())->map(fn ($value) => $value ?? '')->all());
        $resident->save();

        // 与 show() 保持一致的完整视图，小程序端会直接把该响应当缓存用
        return ResidentResource::make($resident->load('affiliatedParty'));
    }

    /**
     * 标记「我牵头的 / 我参与的」列表已读：打开列表页时调用，清掉「我的」页红点。
     */
    public function markSeen(Request $request): ResidentResource
    {
        $validated = $request->validate([
            'kind' => ['required', Rule::in(['mine', 'joined'])],
        ]);

        $resident = $this->resident($request);
        $resident->forceFill([$validated['kind'].'_seen_at' => now()])->save();

        $matters = $validated['kind'] === 'mine'
            ? Matter::whereBelongsTo($resident, 'initiator')->get()
            : Matter::whereHas('joins', fn ($query) => $query->whereBelongsTo($resident, 'resident'))->get();
        $matters->each(fn (Matter $matter) => $matter->reads()->updateOrCreate(
            ['resident_id' => $resident->id],
            ['seen_at' => now()],
        ));

        return ResidentResource::make($resident->load('affiliatedParty'));
    }

    /**
     * 微信授权组件（open-type="getPhoneNumber"）拿到的 code 换真实号码，仅解析返回供前端预填，
     * 不在此落库；用户可再手动改号，最终随资料一并保存（走 /me PUT）。
     *
     * @return array{data: array{phone: string}}
     */
    public function resolvePhone(Request $request, WeChat $weChat): array
    {
        $validated = $request->validate(['code' => ['required', 'string']]);

        return ['data' => ['phone' => $weChat->phoneNumberFromCode($validated['code'])]];
    }
}
