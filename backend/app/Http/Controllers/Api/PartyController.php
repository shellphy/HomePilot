<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesResident;
use App\Http\Controllers\Controller;
use App\Http\Resources\ResidentResource;
use App\Models\Party;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PartyController extends Controller
{
    use ResolvesResident;

    /**
     * 相关方入驻：创建一个相关方并绑定到当前成员（管理员认证后 is_listed 才为真）。
     * 可自助入驻的类型由 Party::TYPES 声明，前端入驻页自动跟随。
     */
    public function store(Request $request): ResidentResource
    {
        $resident = $this->resident($request);

        $selfRegistrable = collect(Party::TYPES)
            ->filter(fn (array $meta): bool => $meta['self_registrable'])
            ->keys()
            ->all();

        $validated = $request->validate([
            'type' => ['required', Rule::in($selfRegistrable)],
            'name' => ['required', 'string', 'max:50'],
            'category' => ['sometimes', 'nullable', 'string', 'max:30'],
        ], [
            'type.required' => '请选择身份类型',
            'name.required' => '请填写名称',
        ]);

        // 已是同类型身份：更新现有相关方档案，而不是每次保存都新建一个
        $party = $resident->party;
        if ($party && $party->type === $validated['type']) {
            $party->update([
                'name' => $validated['name'],
                'category' => $validated['category'] ?? '',
            ]);
        } else {
            $party = Party::create([
                'type' => $validated['type'],
                'name' => $validated['name'],
                'category' => $validated['category'] ?? '',
            ]);
            $resident->update(['party_id' => $party->id]);
        }

        return ResidentResource::make($resident->load(['unit', 'party']));
    }

    /**
     * 切回业主身份（解绑相关方；相关方记录保留，供管理端追溯）。
     */
    public function destroy(Request $request): ResidentResource
    {
        $resident = $this->resident($request);
        $resident->update(['party_id' => null]);

        return ResidentResource::make($resident->load(['unit', 'party']));
    }
}
