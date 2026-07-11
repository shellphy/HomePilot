<?php

namespace App\Listeners;

use App\Events\MatterDealPosted;
use App\Services\SubscribeNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendMatterDealPostedNotification implements ShouldQueue
{
    public function __construct(private SubscribeNotifier $notifier) {}

    public function handle(MatterDealPosted $event): void
    {
        $this->notifier->dealPosted($event->matter);
    }
}
