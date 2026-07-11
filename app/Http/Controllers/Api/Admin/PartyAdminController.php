<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Party;
use App\Models\Resident;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 管理端 · 相关方：查看入驻档案、认证进公示名单（is_listed）。
 * 商家/物业/业委会全部自助入驻（/me/party），这里只负责认证。
 */
class PartyAdminController extends Controller
{
    public function index(): JsonResponse
    {
        $parties = Party::latest()->get();

        // 入驻方的联系电话：档案归属人（last_party_id）优先，老数据兜底当前绑定人
        $owners = Resident::query()
            ->where(fn ($query) => $query
                ->whereIn('last_party_id', $parties->pluck('id'))
                ->orWhereIn('affiliated_party_id', $parties->pluck('id')))
            ->get(['affiliated_party_id', 'last_party_id', 'phone']);

        $data = $parties->map(function (Party $party) use ($owners): array {
            $owner = $owners->firstWhere('last_party_id', $party->id)
                ?? $owners->firstWhere('affiliated_party_id', $party->id);

            return [
                'id' => $party->id,
                'type' => $party->type,
                'type_label' => $party->typeLabel(),
                'name' => $party->name,
                'category' => $party->category,
                'phone' => $owner?->phone,
                'is_listed' => $party->is_listed,
                'created_at' => $party->created_at?->format('Y-m-d H:i'),
            ];
        });

        // 待认证 = 有归属人亮明了身份但还没被认证的（空壳档案不算待办），喂给「我的」页角标
        $pendingCount = $parties
            ->filter(fn (Party $party): bool => ! $party->is_listed
                && ($owners->firstWhere('last_party_id', $party->id)
                    ?? $owners->firstWhere('affiliated_party_id', $party->id)) !== null)
            ->count();

        return response()->json(['data' => $data, 'pending_count' => $pendingCount]);
    }

    public function update(Request $request, Party $party): JsonResponse
    {
        $validated = $request->validate(['is_listed' => ['required', 'boolean']]);

        $party->update($validated);

        return response()->json(['data' => ['id' => $party->id, 'is_listed' => $party->is_listed]]);
    }
}
