<?php

namespace App\Events;

use App\Models\Matter;
use App\Models\Stance;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * 有人加入接龙名单（报名/响应互助），或把登记的意向升级为确认参团（upgraded）。
 */
class MatterJoined
{
    use Dispatchable, SerializesModels;

    public function __construct(public Matter $matter, public Stance $join, public bool $upgraded = false) {}
}
