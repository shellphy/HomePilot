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
     * self_registrable = 可从小程序自助亮明身份（业委会等治理身份由管理员认定）。
     *
     * @var array<string, array{label: string, self_registrable: bool}>
     */
    public const TYPES = [
        self::TYPE_MERCHANT => ['label' => '商家', 'self_registrable' => true],
        // 物业/开发商暂不开放自助入驻（需要时把开关打开即可，前端身份选项自动跟随）
        self::TYPE_PROPERTY => ['label' => '物业', 'self_registrable' => false],
        self::TYPE_DEVELOPER => ['label' => '开发商', 'self_registrable' => false],
        self::TYPE_COMMITTEE => ['label' => '业委会', 'self_registrable' => false],
    ];

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

    protected $fillable = ['type', 'name', 'category', 'is_listed'];

    protected function casts(): array
    {
        return [
            'is_listed' => 'boolean',
        ];
    }

    /** @return HasMany<Resident, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(Resident::class, 'affiliated_party_id');
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
