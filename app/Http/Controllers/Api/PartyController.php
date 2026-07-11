<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesResident;
use App\Http\Controllers\Controller;
use App\Http\Resources\ResidentResource;
use App\Models\Party;
use App\Models\Stance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PartyController extends Controller
{
    use ResolvesResident;

    /**
     * 已认证商家名录（面向全小区）：带发起事项数、成团数与评价沉淀。
     * 只收商家——物业/业委会等治理身份的公开面是事项时间线里的官方回应署名，不进名录。
     */
    public function index(): JsonResponse
    {
        $parties = Party::where('is_listed', true)
            ->where('type', Party::TYPE_MERCHANT)
            ->withCount(['initiatedMatters as matter_count' => fn ($query) => $query->where('is_approved', true)])
            ->withCount(['initiatedMatters as deal_count' => fn ($query) => $query->where('type', 'groupbuy')->where('state', 'done')])
            ->orderByDesc('deal_count')
            ->get();

        // 评价挂在事项上，按发起方聚合出每家的均分（小区体量小，PHP 侧聚合即可）
        $reviews = Stance::where('mode', Stance::MODE_REVIEW)
            ->whereHas('matter', fn ($query) => $query->whereIn('initiator_party_id', $parties->pluck('id')))
            ->with('matter')
            ->get()
            ->groupBy(fn (Stance $review): int => (int) $review->matter->initiator_party_id);

        return response()->json([
            'data' => $parties->map(function (Party $party) use ($reviews): array {
                $partyReviews = $reviews->get($party->id, collect());

                return [
                    'id' => $party->id,
                    'type' => $party->type,
                    'label' => $party->typeLabel(),
                    'name' => $party->name,
                    'category' => $party->category,
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
     * 商家/物业/业委会等全部走这一条链路，可选类型由 Party::TYPES 声明，前端入驻页自动跟随。
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

        // 补充字段只对声明了 category_label 的类型有意义（商家的主营），其余类型忽略提交值
        $category = Party::TYPES[$validated['type']]['category_label'] === ''
            ? ''
            : ($validated['category'] ?? '');

        // 已是同类型身份：更新现有相关方档案，而不是每次保存都新建一个
        $party = $resident->affiliatedParty;
        if ($party && $party->type === $validated['type']) {
            $party->update([
                'name' => $validated['name'],
                'category' => $category,
            ]);
        } else {
            $party = Party::create([
                'type' => $validated['type'],
                'name' => $validated['name'],
                'category' => $category,
            ]);
            $resident->update(['affiliated_party_id' => $party->id]);
        }

        return ResidentResource::make($resident->load('affiliatedParty'));
    }

    /**
     * 切回业主身份（解绑相关方；相关方记录保留，供管理端追溯）。
     */
    public function destroy(Request $request): ResidentResource
    {
        $resident = $this->resident($request);
        $resident->update(['affiliated_party_id' => null]);

        return ResidentResource::make($resident->load('affiliatedParty'));
    }
}
