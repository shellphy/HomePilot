<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Party;
use App\Models\Resident;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * 管理端 · 相关方：查看入驻档案、认证进公示名单（is_listed）、
 * 创建治理类相关方（物业/开发商/业委会不自助入驻，由管理员建档）并绑定成员。
 */
class PartyAdminController extends Controller
{
    public function index(): JsonResponse
    {
        $parties = Party::withCount('members')
            ->with('members:id,affiliated_party_id,nickname,unit_label,phone')
            ->latest()
            ->get()
            ->map(fn (Party $party): array => $this->present($party));

        return response()->json([
            'data' => $parties,
            // 待认证 = 有成员亮明了身份但还没被认证的（空壳档案不算待办）
            'pending_count' => Party::where('is_listed', false)->whereHas('members')->count(),
        ]);
    }

    /**
     * 创建相关方（任何类型）：给物业/业委会这类不自助入驻的身份一个建档通道。
     * 管理员建的档案默认直接认证（is_listed），可显式传 false 先建档后认证。
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(array_keys(Party::TYPES))],
            'name' => ['required', 'string', 'max:50'],
            'category' => ['sometimes', 'nullable', 'string', 'max:30'],
            'is_listed' => ['sometimes', 'boolean'],
        ], [
            'name.required' => '请填写名称',
        ]);

        $party = Party::create([
            'type' => $validated['type'],
            'name' => $validated['name'],
            'category' => $validated['category'] ?? '',
            'is_listed' => $validated['is_listed'] ?? true,
        ]);

        return response()->json(['data' => $this->present($party->loadCount('members'))], 201);
    }

    public function update(Request $request, Party $party): JsonResponse
    {
        $validated = $request->validate(['is_listed' => ['required', 'boolean']]);

        $party->update($validated);

        return response()->json(['data' => ['id' => $party->id, 'is_listed' => $party->is_listed]]);
    }

    /**
     * 绑定成员到相关方（按成员 ID 或授权手机号找人，与 admin:grant 同一套定位方式）。
     * 一个成员同一时刻只挂一个相关方，重复绑定即改挂。
     */
    public function storeMember(Request $request, Party $party): JsonResponse
    {
        $key = $request->validate([
            'resident' => ['required', 'string', 'max:30'],
        ], [
            'resident.required' => '请填写成员 ID 或手机号',
        ])['resident'];

        $resident = Resident::whereKey($key)->first()
            ?? Resident::where('phone', $key)->where('phone', '!=', '')->first();

        if (! $resident) {
            throw ValidationException::withMessages([
                'resident' => "找不到成员：{$key}（可用 ID 或手机号，手机号需成员先在「个人资料」里授权）",
            ]);
        }

        $resident->update(['affiliated_party_id' => $party->id]);

        return response()->json(['data' => $this->present($party->refresh()->loadCount('members'))]);
    }

    /**
     * 从相关方解绑成员（成员回到业主身份，相关方档案保留）。
     */
    public function destroyMember(Party $party, Resident $resident): JsonResponse
    {
        abort_unless($resident->affiliated_party_id === $party->id, 404);

        $resident->update(['affiliated_party_id' => null]);

        return response()->json(['data' => $this->present($party->refresh()->loadCount('members'))]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Party $party): array
    {
        return [
            'id' => $party->id,
            'type' => $party->type,
            'type_label' => $party->typeLabel(),
            'name' => $party->name,
            'category' => $party->category,
            'is_listed' => $party->is_listed,
            'members_count' => (int) ($party->members_count ?? $party->members->count()),
            'members' => $party->members
                ->map(fn (Resident $member): array => [
                    'id' => $member->id,
                    'nickname' => $member->nickname,
                    'unit_label' => $member->unit_label,
                    'phone' => $member->phone,
                ])
                ->values(),
            'created_at' => $party->created_at?->format('Y-m-d H:i'),
        ];
    }
}
