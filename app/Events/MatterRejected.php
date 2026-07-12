<?php

namespace App\Events;

use App\Models\Matter;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * 事项被驳回/撤下（理由在 reject_reason 列，发起人编辑后即重新提交）。
 */
class MatterRejected
{
    use Dispatchable, SerializesModels;

    public function __construct(public Matter $matter) {}
}
