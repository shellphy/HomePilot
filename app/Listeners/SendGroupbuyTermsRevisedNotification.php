<?php

namespace App\Listeners;

use App\Events\GroupbuyTermsRevised;
use App\Services\SubscribeNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendGroupbuyTermsRevisedNotification implements ShouldQueue
{
    public function __construct(private SubscribeNotifier $notifier) {}

    public function handle(GroupbuyTermsRevised $event): void
    {
        $this->notifier->termsRevised($event->matter, $event->residentIds);
    }
}
