<?php

namespace App\Events;

use App\Models\Matter;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * 事项状态流转（成团/谈崩/有结果…）。订阅消息上线后在此挂通知参与者的 listener。
 */
class MatterStateChanged
{
    use Dispatchable;

    public function __construct(public Matter $matter, public string $previousState) {}
}
