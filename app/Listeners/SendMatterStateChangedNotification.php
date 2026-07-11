<?php

namespace App\Listeners;

use App\Events\MatterStateChanged;
use App\Services\SubscribeNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendMatterStateChangedNotification implements ShouldQueue
{
    public function __construct(private SubscribeNotifier $notifier) {}

    public function handle(MatterStateChanged $event): void
    {
        $this->notifier->stateChanged($event->matter, $event->actor);
    }
}
