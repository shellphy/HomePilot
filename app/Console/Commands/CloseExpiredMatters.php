<?php

namespace App\Console\Commands;

use App\Events\MatterStateChanged;
use App\Models\Matter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

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
                $newState = $matter->type === 'activity' ? 'done' : 'closed';
                $matter->update(['state' => $newState]);
                $matter->recordActivity(null);
                MatterStateChanged::dispatch($matter, $previousState);
                $closed++;

                Log::info('定时关闭到期事项', [
                    'matter_id' => $matter->id,
                    'type' => $matter->type,
                    'from' => $previousState,
                    'to' => $newState,
                ]);
            });

        $this->components->info("Closed {$closed} expired matters.");

        return self::SUCCESS;
    }
}
