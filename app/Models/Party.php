<?php

namespace App\Models;

use App\Enums\PartyReviewStatus;
use Database\Factories\PartyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * 相关方：与社区发生关系的组织（商家/物业/开发商/业委会）。
 *
 * @property int $id
 * @property string $type
 * @property string $name
 * @property string $category
 * @property string $intro
 * @property string|null $description
 * @property array<int, string>|null $images
 * @property PartyReviewStatus $review_status
 * @property string $reject_reason
 * @property-read bool $is_listed 派生：review_status 是否为已认证
 */
class Party extends Model
{
    /** @use HasFactory<PartyFactory> */
    use HasFactory;

    public const TYPE_MERCHANT = 'merchant';

    public const TYPE_PROPERTY = 'property';

    public const TYPE_DEVELOPER = 'developer';

    public const TYPE_COMMITTEE = 'committee';

    /**
     * 相关方类型注册表：加一种类型改这里即可，API 与小程序入驻页自动跟随。
     * 所有类型统一走「自助亮明身份 → 管理员认证」一条链路。
     * name_hint = 入驻表单名称栏的提示；category_label = 档案补充字段的标签
     * （空 = 该类型没有补充字段，主营品类只对商家有意义）；
     * description_hint = 详细介绍的引导语（简介/详细介绍/照片各类型统一，内容自由发挥）。
     *
     * @var array<string, array{label: string, self_registrable: bool, name_hint: string, category_label: string, description_hint: string}>
     */
    public const TYPES = [
        self::TYPE_MERCHANT => ['label' => '商家', 'self_registrable' => true, 'name_hint' => '店名/公司名，如：青城中央空调', 'category_label' => '主营', 'description_hint' => '店面地址、主营产品、服务过哪些小区、售后承诺……'],
        self::TYPE_PROPERTY => ['label' => '物业', 'self_registrable' => true, 'name_hint' => '如：天青府物业服务中心', 'category_label' => '', 'description_hint' => '哪些问题可以找物业、服务时间、报修与投诉渠道……'],
        self::TYPE_DEVELOPER => ['label' => '开发商', 'self_registrable' => true, 'name_hint' => '如：青城置业', 'category_label' => '', 'description_hint' => '负责哪些交付问题、对接与反馈渠道……'],
        self::TYPE_COMMITTEE => ['label' => '业委会', 'self_registrable' => true, 'name_hint' => '如：天青府业主委员会', 'category_label' => '', 'description_hint' => '业委会职责、如何联系、正在推进的事……'],
    ];

    protected $fillable = ['type', 'name', 'category', 'intro', 'description', 'images', 'review_status', 'reject_reason'];

    protected function casts(): array
    {
        return [
            'images' => 'array',
            'review_status' => PartyReviewStatus::class,
        ];
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type]['label'] ?? $this->type;
    }

    /**
     * 公示态 = 认证通过：名录、发起权、官方回应都以此为准。
     *
     * @return Attribute<bool, never>
     */
    protected function isListed(): Attribute
    {
        return Attribute::get(fn (): bool => $this->review_status === PartyReviewStatus::Approved);
    }

    public function approve(): void
    {
        $this->update([
            'review_status' => PartyReviewStatus::Approved,
            'reject_reason' => '',
        ]);
    }

    public function reject(string $reason): void
    {
        $this->update([
            'review_status' => PartyReviewStatus::Rejected,
            'reject_reason' => $reason,
        ]);
    }

    /**
     * @param  Builder<Party>  $query
     * @return Builder<Party>
     */
    public function scopeListed(Builder $query): Builder
    {
        return $query->where('review_status', PartyReviewStatus::Approved->value);
    }

    /**
     * 治理类相关方（物业/开发商/业委会）：被管理员认证（is_listed）后，
     * 其成员可在事项时间线里以官方身份回应。
     */
    public function isGovernance(): bool
    {
        return in_array($this->type, [self::TYPE_PROPERTY, self::TYPE_DEVELOPER, self::TYPE_COMMITTEE], true);
    }

    /**
     * 从候选成员里选出档案联系人：当前绑定的成员优先，其次最近绑定过它的成员（last_party_id）。
     * 名录、详情、管理端共用这一条规则——档案先后被多人绑定过时，电话不能张冠李戴。
     *
     * @param  EloquentCollection<int, Resident>  $candidates  按最近活跃排序（contactCandidatesFor 的产物）
     */
    public function contactOwnerAmong(EloquentCollection $candidates): ?Resident
    {
        return $candidates->firstWhere('affiliated_party_id', $this->id)
            ?? $candidates->firstWhere('last_party_id', $this->id);
    }

    /**
     * 一批相关方的联系人候选（按最近活跃排序），配合 contactOwnerAmong 逐个选人。
     *
     * @param  Collection<array-key, int>  $partyIds
     * @return EloquentCollection<int, Resident>
     */
    public static function contactCandidatesFor(Collection $partyIds, bool $withPhoneOnly = false): EloquentCollection
    {
        return Resident::query()
            ->when($withPhoneOnly, fn ($query) => $query->where('phone', '!=', ''))
            ->where(fn ($query) => $query
                ->whereIn('last_party_id', $partyIds)
                ->orWhereIn('affiliated_party_id', $partyIds))
            ->orderByDesc('updated_at')
            ->get(['id', 'affiliated_party_id', 'last_party_id', 'phone']);
    }

    /**
     * 以该相关方身份发起的事项（身份快照，成员切换身份不影响）。
     *
     * @return HasMany<Matter, $this>
     */
    public function initiatedMatters(): HasMany
    {
        return $this->hasMany(Matter::class, 'initiator_party_id');
    }
}
