<?php

namespace App\Listeners;

use App\Events\PartyRejected;
use App\Services\SubscribeNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendPartyRejectedNotification implements ShouldQueue
{
    public function __construct(private SubscribeNotifier $notifier) {}

    public function handle(PartyRejected $event): void
    {
        $this->notifier->partyRejected($event->party);
    }
}
