<?php

namespace App\Events;

use App\Models\Party;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * 相关方自助入驻（新建或被驳回后改资料重交），进入认证队列等管理员认证。
 */
class PartyRegistered
{
    use Dispatchable, SerializesModels;

    public function __construct(public Party $party) {}
}
