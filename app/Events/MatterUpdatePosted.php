<?php

namespace App\Events;

use App\Models\MatterUpdate;
use App\Models\Resident;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * 发起人发布了进度更新（或治理方发布官方回应）。author = 发布人，通知时排除。
 */
class MatterUpdatePosted
{
    use Dispatchable, SerializesModels;

    public function __construct(public MatterUpdate $update, public ?Resident $author = null) {}
}
