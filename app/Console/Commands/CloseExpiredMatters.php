<?php

namespace App\Console\Commands;

use App\Events\MatterStateChanged;
use App\Models\Matter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('matters:close-expired')]
#[Description('Close activities and aid matters after their scheduled start time')]
class CloseExpiredMatters extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $closed = 0;

        Matter::query()
            ->whereIn('type', ['activity', 'aid'])
            ->where('state', 'open')
            ->whereNotNull('starts_at')
            ->where('starts_at', '<=', now())
            ->eachById(function (Matter $matter) use (&$closed): void {
                $previousState = $matter->state;
                $matter->update(['state' => $matter->type === 'activity' ? 'done' : 'closed']);
                $matter->recordActivity(null);
                MatterStateChanged::dispatch($matter, $previousState);
                $closed++;
            });

        $this->components->info("Closed {$closed} expired matters.");

        return self::SUCCESS;
    }
}
