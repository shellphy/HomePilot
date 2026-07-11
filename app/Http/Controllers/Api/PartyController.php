<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesResident;
use App\Http\Controllers\Controller;
use App\Http\Resources\ResidentResource;
use App\Models\Party;
use App\Models\Resident;
use App\Models\Stance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PartyController extends Controller
{
    use ResolvesResident;

    /**
     * 已认证相关方名录（面向全小区）：商家带发起事项数、成团数与评价沉淀。
     */
    public function index(): JsonResponse
    {
        $parties = Party::where('is_listed', true)
            ->withCount(['initiatedMatters as matter_count' => fn ($query) => $query->where('is_approved', true)])
            ->withCount(['initiatedMatters as deal_count' => fn ($query) => $query->where('type', 'groupbuy')->where('state', 'done')])
            ->orderByDesc('deal_count')
            ->get();

        // 联系电话取档案归属人（last_party_id）的授权手机号，老数据兜底当前绑定人；
        // 归属人暂时切回业主也不影响名录展示
        $owners = Resident::query()
            ->where('phone', '!=', '')
            ->where(fn ($query) => $query
                ->whereIn('last_party_id', $parties->pluck('id'))
                ->orWhereIn('affiliated_party_id', $parties->pluck('id')))
            ->get(['affiliated_party_id', 'last_party_id', 'phone']);

        // 评价挂在事项上，按发起方聚合出每家的均分（小区体量小，PHP 侧聚合即可）
        $reviews = Stance::where('mode', Stance::MODE_REVIEW)
            ->whereHas('matter', fn ($query) => $query->whereIn('initiator_party_id', $parties->pluck('id')))
            ->with('matter')
            ->get()
            ->groupBy(fn (Stance $review): int => (int) $review->matter->initiator_party_id);

        return response()->json([
            'data' => $parties->map(function (Party $party) use ($reviews, $owners): array {
                $partyReviews = $reviews->get($party->id, collect());
                $owner = $owners->firstWhere('last_party_id', $party->id)
                    ?? $owners->firstWhere('affiliated_party_id', $party->id);

                return [
                    'id' => $party->id,
                    'type' => $party->type,
                    'label' => $party->typeLabel(),
                    'name' => $party->name,
                    'category' => $party->category,
                    // 已认证相关方公开联系电话——入驻本身就是求曝光，电话是最直接的转化
                    'phone' => $owner?->phone,
                    'matter_count' => (int) $party->getAttribute('matter_count'),
                    'deal_count' => (int) $party->getAttribute('deal_count'),
                    'review_count' => $partyReviews->count(),
                    'rating' => $partyReviews->isEmpty()
                        ? null
                        : round($partyReviews->avg(fn (Stance $review) => (int) ($review->payload['rating'] ?? 0)), 1),
                ];
            }),
        ]);
    }

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

        // 档案跟人走：当前绑定的、或上次切走时留下的同类型档案都直接复用
        // （资料和认证状态原样保留），只有真正第一次入驻才新建
        $party = $resident->affiliatedParty;
        if (! $party || $party->type !== $validated['type']) {
            $last = $resident->lastParty()->first();
            $party = ($last && $last->type === $validated['type']) ? $last : null;
        }

        if ($party) {
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
        }
        $resident->update(['affiliated_party_id' => $party->id, 'last_party_id' => $party->id]);

        return ResidentResource::make($resident->load('affiliatedParty'));
    }

    /**
     * 切回业主身份：只解绑、不删档案。last_party_id 记着它，
     * 再次入驻同类型身份时原样找回（资料、认证状态都在），
     * 认证队列里也始终只有这一条档案。
     */
    public function destroy(Request $request): ResidentResource
    {
        $resident = $this->resident($request);
        $resident->update([
            'affiliated_party_id' => null,
            'last_party_id' => $resident->affiliated_party_id ?? $resident->last_party_id,
        ]);

        return ResidentResource::make($resident->load('affiliatedParty'));
    }
}
