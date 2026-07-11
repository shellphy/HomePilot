<?php

namespace App\Events;

use App\Models\Matter;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * 团购成交公示发布（最终条件 + 返点让利去向），成交名单上的参与者该来看一眼。
 */
class MatterDealPosted
{
    use Dispatchable, SerializesModels;

    public function __construct(public Matter $matter) {}
}
