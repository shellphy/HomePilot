<?php

namespace App\Listeners;

use App\Events\MatterReviewed;
use App\Services\SubscribeNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendMatterReviewedNotification implements ShouldQueue
{
    public function __construct(private SubscribeNotifier $notifier) {}

    public function handle(MatterReviewed $event): void
    {
        $this->notifier->reviewed($event->matter, $event->review);
    }
}
