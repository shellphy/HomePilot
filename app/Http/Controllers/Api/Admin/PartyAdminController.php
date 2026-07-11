<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Party;
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
        $parties = Party::withCount('members')
            ->latest()
            ->get()
            ->map(fn (Party $party): array => [
                'id' => $party->id,
                'type' => $party->type,
                'type_label' => $party->typeLabel(),
                'name' => $party->name,
                'category' => $party->category,
                'is_listed' => $party->is_listed,
                'members_count' => (int) $party->members_count,
                'created_at' => $party->created_at?->format('Y-m-d H:i'),
            ]);

        return response()->json([
            'data' => $parties,
            // 待认证 = 有成员亮明了身份但还没被认证的（空壳档案不算待办）
            'pending_count' => Party::where('is_listed', false)->whereHas('members')->count(),
        ]);
    }

    public function update(Request $request, Party $party): JsonResponse
    {
        $validated = $request->validate(['is_listed' => ['required', 'boolean']]);

        $party->update($validated);

        return response()->json(['data' => ['id' => $party->id, 'is_listed' => $party->is_listed]]);
    }
}
