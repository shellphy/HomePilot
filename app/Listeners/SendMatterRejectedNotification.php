<?php

namespace App\Listeners;

use App\Events\MatterRejected;
use App\Services\SubscribeNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendMatterRejectedNotification implements ShouldQueue
{
    public function __construct(private SubscribeNotifier $notifier) {}

    public function handle(MatterRejected $event): void
    {
        $this->notifier->matterRejected($event->matter);
    }
}
