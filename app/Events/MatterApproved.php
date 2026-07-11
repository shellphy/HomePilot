<?php

namespace App\Events;

use App\Models\Matter;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * 事项过审、对全小区公示。订阅消息上线后在此挂通知发起人的 listener。
 */
class MatterApproved
{
    use Dispatchable;

    public function __construct(public Matter $matter) {}
}
