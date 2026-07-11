<?php

namespace App\Http\Controllers\Api\Admin;

use App\Events\PartyListed;
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
        $parties = Party::latest()->get();

        // 入驻方的联系电话：当前绑定成员优先，其次最近绑定过的成员（规则统一在 Party 模型）
        $owners = Party::contactCandidatesFor($parties->pluck('id'));

        $data = $parties->map(function (Party $party) use ($owners): array {
            $owner = $party->contactOwnerAmong($owners);

            return [
                'id' => $party->id,
                'type' => $party->type,
                'type_label' => $party->typeLabel(),
                'name' => $party->name,
                'category' => $party->category,
                'intro' => $party->intro,
                'phone' => $owner?->phone,
                'is_listed' => $party->is_listed,
                'created_at' => $party->created_at?->format('Y-m-d H:i'),
            ];
        });

        // 待认证 = 有归属人亮明了身份但还没被认证的（空壳档案不算待办），喂给「我的」页角标
        $pendingCount = $parties
            ->filter(fn (Party $party): bool => ! $party->is_listed && $party->contactOwnerAmong($owners) !== null)
            ->count();

        return response()->json(['data' => $data, 'pending_count' => $pendingCount]);
    }

    public function update(Request $request, Party $party): JsonResponse
    {
        $validated = $request->validate(['is_listed' => ['required', 'boolean']]);

        $wasListed = $party->is_listed;
        $party->update($validated);

        if ($party->is_listed && ! $wasListed) {
            PartyListed::dispatch($party);
        }

        return response()->json(['data' => ['id' => $party->id, 'is_listed' => $party->is_listed]]);
    }
}
