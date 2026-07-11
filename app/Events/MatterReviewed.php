<?php

namespace App\Events;

use App\Models\Matter;
use App\Models\Stance;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * 参与者发布了一条新评价（修改已有评价不重复通知）。
 */
class MatterReviewed
{
    use Dispatchable, SerializesModels;

    public function __construct(public Matter $matter, public Stance $review) {}
}
