<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    public function up(): void
    {
        $updatedMatters = 0;
        $updatedModules = 0;
        $updatedQuestions = 0;

        DB::table('matters')
            ->select(['id', 'payload'])
            ->where('type', 'census')
            ->whereNotNull('payload')
            ->orderBy('id')
            ->each(function (object $matter) use (&$updatedMatters, &$updatedModules, &$updatedQuestions): void {
                /** @var array{modules?: array<int, array{intro?: mixed, questions?: array<int, array<string, mixed>>}>} $payload */
                $payload = json_decode((string) $matter->payload, true, flags: JSON_THROW_ON_ERROR);
                $changed = false;

                foreach ($payload['modules'] ?? [] as $moduleIndex => $module) {
                    if (array_key_exists('intro', $module)) {
                        unset($payload['modules'][$moduleIndex]['intro']);
                        $changed = true;
                        $updatedModules++;
                    }

                    foreach ($module['questions'] ?? [] as $questionIndex => $question) {
                        if (array_key_exists('note', $question)) {
                            unset($payload['modules'][$moduleIndex]['questions'][$questionIndex]['note']);
                            $changed = true;
                            $updatedQuestions++;
                        }
                    }
                }

                if (! $changed) {
                    return;
                }

                DB::table('matters')
                    ->where('id', $matter->id)
                    ->update(['payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
                $updatedMatters++;
            });

        if ($updatedModules > 0 || $updatedQuestions > 0) {
            Log::info('问卷说明清理完成', [
                'matters_updated' => $updatedMatters,
                'modules_updated' => $updatedModules,
                'questions_updated' => $updatedQuestions,
            ]);
        }
    }

    public function down(): void
    {
        // 已删除的说明无法准确恢复；需要回退时使用上线前备份。
    }
};
