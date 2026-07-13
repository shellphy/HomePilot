<?php

namespace App\Enums;

/**
 * 相关方入驻的认证状态：待认证 → 认证通过公示 / 驳回；驳回后编辑资料即重新回到待认证。
 */
enum PartyReviewStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => '待认证',
            self::Approved => '已认证',
            self::Rejected => '未通过',
        };
    }
}
