<?php

namespace App\Events;

use App\Models\MatterUpdate;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * 发起人发布了进度更新。订阅消息上线后在此挂通知参与者的 listener。
 */
class MatterUpdatePosted
{
    use Dispatchable;

    public function __construct(public MatterUpdate $update) {}
}
