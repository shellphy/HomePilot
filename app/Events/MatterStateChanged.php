<?php

namespace App\Events;

use App\Models\Matter;
use App\Models\Resident;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * 事项状态流转（成团/谈崩/有结果…）。actor = 流转操作人（发起人或管理员），通知时排除。
 */
class MatterStateChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(public Matter $matter, public string $previousState, public ?Resident $actor = null) {}
}
