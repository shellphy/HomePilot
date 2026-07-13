<?php

namespace App\Events;

use App\Models\Party;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * 相关方（商家/物业/业委会）通过管理员核验，进入小区公示名录。
 */
class PartyListed
{
    use Dispatchable, SerializesModels;

    public function __construct(public Party $party) {}
}
