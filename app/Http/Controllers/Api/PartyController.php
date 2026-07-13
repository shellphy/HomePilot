<?php

namespace App\Http\Controllers\Api;

use App\Enums\PartyReviewStatus;
use App\Events\PartyRegistered;
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
        $parties = Party::listed()
            ->where('type', Party::TYPE_MERCHANT)
            ->withCount(['initiatedMatters as matter_count' => fn ($query) => $query->approved()])
            ->withCount(['initiatedMatters as deal_count' => fn ($query) => $query->where('type', 'groupbuy')->where('state', 'done')])
            ->orderByDesc('deal_count')
            ->get();

        // 联系电话取档案联系人（当前绑定成员优先，其次最近绑定过的成员）的授权手机号；
        // 归属人暂时切回业主也不影响名录展示
        $owners = Party::contactCandidatesFor($parties->pluck('id'), withPhoneOnly: true);

        // 评价挂在事项上，按发起方聚合出每家的均分（小区体量小，PHP 侧聚合即可）
        $reviews = Stance::where('mode', Stance::MODE_REVIEW)
            ->whereHas('matter', fn ($query) => $query->whereIn('initiator_party_id', $parties->pluck('id')))
            ->with('matter')
            ->get()
            ->groupBy(fn (Stance $review): int => (int) $review->matter->initiator_party_id);

        return response()->json([
            'data' => $parties->map(function (Party $party) use ($reviews, $owners): array {
                $partyReviews = $reviews->get($party->id, collect());
                $owner = $party->contactOwnerAmong($owners);

                return [
                    'id' => $party->id,
                    'type' => $party->type,
                    'label' => $party->typeLabel(),
                    'name' => $party->name,
                    'category' => $party->category,
                    'intro' => $party->intro,
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
     * 相关方详情页：名录点入（已认证对全小区可见）；
     * 未认证的档案只有管理员（审核前看资料）和归属人自己（预览）能看。
     */
    public function show(Request $request, Party $party): JsonResponse
    {
        $resident = $this->resident($request);

        $isOwner = $resident->affiliated_party_id === $party->id || $resident->last_party_id === $party->id;
        abort_unless($party->is_listed || $resident->is_admin || $isOwner, 404);

        $owner = $party->contactOwnerAmong(
            Party::contactCandidatesFor(collect([$party->id]), withPhoneOnly: true),
        );

        $reviews = Stance::where('mode', Stance::MODE_REVIEW)
            ->whereHas('matter', fn ($query) => $query->where('initiator_party_id', $party->id))
            ->get();

        return response()->json([
            'data' => [
                'id' => $party->id,
                'type' => $party->type,
                'label' => $party->typeLabel(),
                'name' => $party->name,
                'category' => $party->category,
                'intro' => $party->intro,
                'description' => $party->description ?? '',
                'images' => $party->images ?? [],
                'is_listed' => $party->is_listed,
                'review_status' => $party->review_status->value,
                'review_status_label' => $party->review_status->label(),
                'reject_reason' => $party->reject_reason,
                'reviewed_on' => $party->reviewed_at?->format('Y 年 m 月'),
                'phone' => $owner?->phone,
                'matter_count' => $party->initiatedMatters()->approved()->count(),
                'deal_count' => $party->initiatedMatters()->where('type', 'groupbuy')->where('state', 'done')->count(),
                'review_count' => $reviews->count(),
                'rating' => $reviews->isEmpty()
                    ? null
                    : round($reviews->avg(fn (Stance $review) => (int) ($review->payload['rating'] ?? 0)), 1),
                'created_at' => $party->created_at?->format('Y-m-d'),
            ],
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
            // 自我介绍（各类型统一，内容自由发挥）：简介上名录列表行，详细介绍和照片进详情页
            'intro' => ['sometimes', 'nullable', 'string', 'max:60'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'images' => ['sometimes', 'nullable', 'array', 'max:9'],
            'images.*' => ['required', 'string', 'max:300'],
        ], [
            'type.required' => '请选择身份类型',
            'name.required' => '请填写名称',
        ]);

        // 补充字段只对声明了 category_label 的类型有意义（商家的主营），其余类型忽略提交值
        $category = Party::TYPES[$validated['type']]['category_label'] === ''
            ? ''
            : ($validated['category'] ?? '');

        $profile = [
            'name' => $validated['name'],
            'category' => $category,
            'intro' => $validated['intro'] ?? '',
            'description' => $validated['description'] ?? '',
            'images' => $validated['images'] ?? [],
        ];

        // 档案跟人走：当前绑定的、或上次切走时留下的同类型档案都直接复用，只有真正第一次入驻才新建
        $party = $resident->affiliatedParty;
        if (! $party || $party->type !== $validated['type']) {
            $last = $resident->lastParty()->first();
            $party = ($last && $last->type === $validated['type']) ? $last : null;
        }

        // 新建，或已认证/已驳回的档案改了公开资料 → 进（回）待认证队列并提醒管理员；
        // 原样切回身份（资料没变）保留原认证状态
        $enteredQueue = false;
        if ($party) {
            $changed = [
                'name' => $party->name,
                'category' => $party->category ?? '',
                'intro' => $party->intro ?? '',
                'description' => $party->description ?? '',
                'images' => $party->images ?? [],
            ] !== $profile;
            $requeue = $changed && $party->review_status !== PartyReviewStatus::Pending;
            $party->update($requeue
                ? [...$profile, 'review_status' => PartyReviewStatus::Pending, 'reject_reason' => '']
                : $profile);
            $enteredQueue = $requeue;
        } else {
            $party = Party::create(['type' => $validated['type'], ...$profile]);
            $enteredQueue = true;
        }
        $resident->update(['affiliated_party_id' => $party->id, 'last_party_id' => $party->id]);

        if ($enteredQueue) {
            PartyRegistered::dispatch($party);
        }

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
