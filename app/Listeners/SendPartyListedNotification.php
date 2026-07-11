<?php

namespace App\Listeners;

use App\Events\PartyListed;
use App\Services\SubscribeNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendPartyListedNotification implements ShouldQueue
{
    public function __construct(private SubscribeNotifier $notifier) {}

    public function handle(PartyListed $event): void
    {
        $this->notifier->partyListed($event->party);
    }
}
