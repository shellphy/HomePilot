<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\PartyReviewStatus;
use App\Events\PartyListed;
use App\Events\PartyRejected;
use App\Http\Controllers\Api\Concerns\ResolvesResident;
use App\Http\Controllers\Controller;
use App\Models\Party;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * 管理端 · 相关方：查看入驻档案，核验通过公示 / 驳回附理由。
 * 商家/物业/业委会全部自助入驻（/me/party），这里只负责审核。
 */
class PartyAdminController extends Controller
{
    use ResolvesResident;

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
                'review_status' => $party->review_status->value,
                'review_status_label' => $party->review_status->label(),
                'reject_reason' => $party->reject_reason,
                'created_at' => $party->created_at?->format('Y-m-d H:i'),
            ];
        });

        // 待核验 = 有归属人亮明了身份且还在待核验队列的（驳回态在等归属人改，空壳档案不算待办）
        $pendingCount = $parties
            ->filter(fn (Party $party): bool => $party->review_status === PartyReviewStatus::Pending
                && $party->contactOwnerAmong($owners) !== null)
            ->count();

        return response()->json(['data' => $data, 'pending_count' => $pendingCount]);
    }

    public function update(Request $request, Party $party): JsonResponse
    {
        $validated = $request->validate([
            'is_approved' => ['required', 'boolean'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:200'],
        ]);

        $wasListed = $party->is_listed;

        if ($validated['is_approved']) {
            $party->approve();
        } else {
            $party->reject($validated['reason'] ?? '');
        }

        // 档案只留 reviewed_at，核验人不落库
        Log::info('审计 · 相关方核验', [
            'actor_id' => $this->resident($request)->id,
            'party_id' => $party->id,
            'approved' => $validated['is_approved'],
            'was_listed' => $wasListed,
            'has_reason' => filled($validated['reason'] ?? ''),
        ]);

        if ($party->is_listed && ! $wasListed) {
            PartyListed::dispatch($party);
        }

        if (! $validated['is_approved']) {
            PartyRejected::dispatch($party);
        }

        return response()->json(['data' => [
            'id' => $party->id,
            'is_listed' => $party->is_listed,
            'review_status' => $party->review_status->value,
            'reject_reason' => $party->reject_reason,
        ]]);
    }
}
