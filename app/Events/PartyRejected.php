<?php

namespace App\Events;

use App\Models\Party;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * 相关方核验被驳回（附理由），通知归属人改资料后重交。
 */
class PartyRejected
{
    use Dispatchable, SerializesModels;

    public function __construct(public Party $party) {}
}
