<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $censuses = DB::table('matters')
            ->where('type', 'census')
            ->where('review_status', 'pending')
            ->get(['id', 'payload']);

        foreach ($censuses as $census) {
            $payload = json_decode((string) $census->payload, true);

            if ($this->hasQuestions(is_array($payload) ? $payload : [])) {
                continue;
            }

            DB::table('matters')
                ->where('id', $census->id)
                ->update(['review_status' => 'draft']);
        }
    }

    public function down(): void
    {
        // Intentionally irreversible: new empty drafts must not enter review on rollback.
    }

    /** @param array<string, mixed> $payload */
    private function hasQuestions(array $payload): bool
    {
        $modules = $payload['modules'] ?? [];

        if (! is_array($modules)) {
            return false;
        }

        foreach ($modules as $module) {
            if (! is_array($module)) {
                continue;
            }

            $questions = $module['questions'] ?? null;

            if (is_array($questions) && $questions !== []) {
                return true;
            }
        }

        return false;
    }
};
