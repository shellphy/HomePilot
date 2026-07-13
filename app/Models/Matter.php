<?php

namespace App\Models;

use App\Enums\MatterReviewStatus;
use Database\Factories\MatterFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * 事项：社区正在处理的一件事（系统的中心运行时对象）。
 *
 * @property int $id
 * @property string $type
 * @property int|null $initiator_id
 * @property int|null $initiator_party_id
 * @property string $title
 * @property string $body
 * @property string $category
 * @property string $state
 * @property MatterReviewStatus $review_status
 * @property string $reject_reason
 * @property-read bool $is_approved 派生：review_status 是否为已公示
 * @property int $target_count
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $last_activity_at
 * @property Carbon|null $starts_at
 * @property Carbon|null $registration_deadline_at
 * @property int|null $last_activity_resident_id
 * @property-read Resident|null $initiator
 * @property-read Party|null $initiatorParty
 */
class Matter extends Model
{
    /** @use HasFactory<MatterFactory> */
    use HasFactory;

    /** 管理员删除为软删除：误删可恢复，表态/评价/时间线一并保留。 */
    use SoftDeletes;

    protected $fillable = [
        'type',
        'initiator_id',
        'initiator_party_id',
        'title',
        'body',
        'category',
        'state',
        'review_status',
        'reject_reason',
        'target_count',
        'payload',
        'starts_at',
        'registration_deadline_at',
        'location',
    ];

    /**
     * 新建实例的默认审核态：业主自发的事项一律先待审核；
     * 让内存中的模型（如刚 create 出来还没回查）也有确定值，避免依赖数据库列默认。
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'review_status' => 'pending',
        'reject_reason' => '',
    ];

    protected function casts(): array
    {
        return [
            'review_status' => MatterReviewStatus::class,
            'payload' => 'array',
            'last_activity_at' => 'datetime',
            'starts_at' => 'datetime',
            'registration_deadline_at' => 'datetime',
        ];
    }

    public function registrationHasClosed(): bool
    {
        return $this->registration_deadline_at !== null && $this->registration_deadline_at->isPast();
    }

    public function hasUnreadActivityFor(Resident $resident): bool
    {
        if ($this->last_activity_at === null || $this->last_activity_resident_id === $resident->id) {
            return false;
        }

        $read = $this->relationLoaded('reads')
            ? $this->reads->firstWhere('resident_id', $resident->id)
            : $this->reads()->whereBelongsTo($resident, 'resident')->first();

        return $read === null || $read->seen_at->lt($this->last_activity_at);
    }

    /**
     * 派生的「是否已公示」布尔：大量「对外可见吗」的判断只关心这一点，
     * 保留为访问器让调用方无需感知三态枚举。
     *
     * @return Attribute<bool, never>
     */
    protected function isApproved(): Attribute
    {
        return Attribute::get(fn (): bool => $this->review_status === MatterReviewStatus::Approved);
    }

    /** 审核通过并公示，清掉可能存在的驳回理由。 */
    public function approve(): void
    {
        $this->update([
            'review_status' => MatterReviewStatus::Approved,
            'reject_reason' => '',
        ]);
    }

    /** 驳回并附理由；发起人在详情页看到，编辑重提后回到待审核。 */
    public function reject(string $reason): void
    {
        $this->update([
            'review_status' => MatterReviewStatus::Rejected,
            'reject_reason' => $reason,
        ]);
    }

    /** 打回待审核（撤下已公示、或驳回后被发起人编辑重新提交）。 */
    public function markPending(): void
    {
        $this->update([
            'review_status' => MatterReviewStatus::Pending,
            'reject_reason' => '',
        ]);
    }

    /**
     * 记录一次对发起人/参与者有意义的动态（审核结果、状态流转、成交公示、时间线进展），
     * 供「我的」页未读红点比对；actor 记下触发人，比对时排除自己触发的动态。
     */
    public function recordActivity(?Resident $actor): void
    {
        $this->forceFill([
            'last_activity_at' => now(),
            'last_activity_resident_id' => $actor?->id,
        ])->save();
    }

    public function payloadValue(string $key, mixed $default = null): mixed
    {
        return $this->payload[$key] ?? $default;
    }

    /**
     * payload 中的列表字段（modules/terms 等），缺失或非法时按空列表处理。
     *
     * @return array<array-key, mixed>
     */
    public function payloadList(string $key): array
    {
        $value = $this->payloadValue($key);

        return is_array($value) ? $value : [];
    }

    public function hasCensusQuestions(): bool
    {
        foreach ($this->payloadList('modules') as $module) {
            if (! is_array($module)) {
                continue;
            }

            $questions = $module['questions'] ?? null;

            if (is_array($questions) && $questions !== []) {
                return true;
            }
        }

        return false;
    }

    /** @return BelongsTo<Resident, $this> */
    public function initiator(): BelongsTo
    {
        return $this->belongsTo(Resident::class, 'initiator_id');
    }

    /**
     * 发起时的相关方身份快照（已核验商家发起的事项带商家署名）。
     *
     * @return BelongsTo<Party, $this>
     */
    public function initiatorParty(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'initiator_party_id');
    }

    /** @return HasMany<Stance, $this> */
    public function stances(): HasMany
    {
        return $this->hasMany(Stance::class);
    }

    /**
     * 接龙表态。
     *
     * @return HasMany<Stance, $this>
     */
    public function joins(): HasMany
    {
        return $this->stances()->where('mode', Stance::MODE_JOIN);
    }

    /**
     * 确认参团的接龙；只有团购按承诺档位筛选，其余类型报名即确认。
     *
     * @return HasMany<Stance, $this>
     */
    public function confirmedJoins(): HasMany
    {
        $joins = $this->joins();

        return $this->type === 'groupbuy'
            ? $joins->where('payload->stage', Stance::JOIN_STAGE_CONFIRMED)
            : $joins;
    }

    /**
     * 评价表态。
     *
     * @return HasMany<Stance, $this>
     */
    public function reviews(): HasMany
    {
        return $this->stances()->where('mode', Stance::MODE_REVIEW);
    }

    /** @return HasMany<MatterUpdate, $this> */
    public function updates(): HasMany
    {
        return $this->hasMany(MatterUpdate::class);
    }

    /** @return HasMany<MatterRead, $this> */
    public function reads(): HasMany
    {
        return $this->hasMany(MatterRead::class);
    }

    /**
     * 「大家都在问」的公开问答。
     *
     * @return HasMany<MatterQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(MatterQuestion::class);
    }

    /**
     * @param  Builder<Matter>  $query
     * @return Builder<Matter>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('review_status', MatterReviewStatus::Approved->value);
    }
}
