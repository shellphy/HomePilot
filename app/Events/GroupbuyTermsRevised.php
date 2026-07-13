<?php

namespace App\Events;

use App\Models\Matter;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * 团购条款/披露实质变更，已确认参团者被降回登记意向：通知他们审核通过后重新确认。
 */
class GroupbuyTermsRevised
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<int, int>  $residentIds  被降级的参团者
     */
    public function __construct(public Matter $matter, public array $residentIds) {}
}
