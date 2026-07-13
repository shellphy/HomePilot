<?php

namespace App\Listeners;

use App\Events\PartyRegistered;
use App\Services\SubscribeNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendPartyRegisteredNotification implements ShouldQueue
{
    public function __construct(private SubscribeNotifier $notifier) {}

    public function handle(PartyRegistered $event): void
    {
        $this->notifier->partyRegistered($event->party);
    }
}
