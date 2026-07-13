<?php

namespace App\Enums;

/**
 * 相关方入驻的核验状态：待核验 → 核验通过公示 / 驳回；驳回后编辑资料即重新回到待核验。
 */
enum PartyReviewStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => '待核验',
            self::Approved => '已核验',
            self::Rejected => '未通过',
        };
    }
}
