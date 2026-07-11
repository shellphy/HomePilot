<?php

namespace App\Models;

use Database\Factories\PartyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
 * @property bool $is_listed
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

    protected $fillable = ['type', 'name', 'category', 'intro', 'description', 'images', 'is_listed'];

    protected function casts(): array
    {
        return [
            'images' => 'array',
            'is_listed' => 'boolean',
        ];
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type]['label'] ?? $this->type;
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
     * 以该相关方身份发起的事项（身份快照，成员切换身份不影响）。
     *
     * @return HasMany<Matter, $this>
     */
    public function initiatedMatters(): HasMany
    {
        return $this->hasMany(Matter::class, 'initiator_party_id');
    }
}
