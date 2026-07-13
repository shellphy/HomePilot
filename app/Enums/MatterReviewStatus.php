<?php

namespace App\Enums;

/**
 * 事项的审核状态：与类型内的业务状态机（state）正交。
 * 草稿 → 待审核 → 通过公示 / 驳回；驳回后发起人编辑即重新回到待审核。
 */
enum MatterReviewStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Draft => '草稿',
            self::Pending => '待审核',
            self::Approved => '已公示',
            self::Rejected => '已驳回',
        };
    }
}
