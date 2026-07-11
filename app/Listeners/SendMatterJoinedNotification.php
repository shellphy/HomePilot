<?php

namespace App\Listeners;

use App\Events\MatterJoined;
use App\Services\SubscribeNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendMatterJoinedNotification implements ShouldQueue
{
    public function __construct(private SubscribeNotifier $notifier) {}

    public function handle(MatterJoined $event): void
    {
        $this->notifier->joined($event->matter, $event->join, $event->upgraded);
    }
}
