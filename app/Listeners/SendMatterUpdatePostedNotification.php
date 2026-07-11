<?php

namespace App\Listeners;

use App\Events\MatterUpdatePosted;
use App\Services\SubscribeNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendMatterUpdatePostedNotification implements ShouldQueue
{
    public function __construct(private SubscribeNotifier $notifier) {}

    public function handle(MatterUpdatePosted $event): void
    {
        $this->notifier->updatePosted($event->update, $event->author);
    }
}
