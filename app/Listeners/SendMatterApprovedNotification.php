<?php

namespace App\Listeners;

use App\Events\MatterApproved;
use App\Services\SubscribeNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendMatterApprovedNotification implements ShouldQueue
{
    public function __construct(private SubscribeNotifier $notifier) {}

    public function handle(MatterApproved $event): void
    {
        $this->notifier->matterApproved($event->matter);
    }
}
