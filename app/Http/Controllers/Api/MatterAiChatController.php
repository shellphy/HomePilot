<?php

namespace App\Http\Controllers\Api;

use App\Ai\Agents\MatterExplainer;
use App\Http\Controllers\Api\Concerns\ResolvesResident;
use App\Http\Controllers\Controller;
use App\Models\Matter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Streaming\Events\Citation;
use Laravel\Ai\Streaming\Events\ProviderToolEvent;
use Laravel\Ai\Streaming\Events\TextDelta;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/** 事项上下文的多轮 AI 答疑，以 SSE 返回结果。 */
class MatterAiChatController extends Controller
{
    use ResolvesResident;

    public function store(Request $request, Matter $matter): StreamedResponse
    {
        $resident = $this->resident($request);

        abort_unless($matter->is_approved || $matter->initiator_id === $resident->id, 404);
        abort_unless(in_array($matter->type, ['groupbuy', 'activity', 'census'], true), 422, '该事项不支持 AI 答疑');

        $validated = $request->validate([
            'question' => ['required', 'string', 'max:300'],
            'conversation_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            // 征集填写页会传入尚未保存的答案。
            'answers' => ['sometimes', 'array'],
        ]);

        $agent = new MatterExplainer($matter, $resident, $validated['answers'] ?? null);
        $conversationId = $validated['conversation_id'] ?? null;

        if ($conversationId !== null) {
            $conversationBelongsToMatter = DB::table(config('ai.conversations.tables.conversations', 'agent_conversations'))
                ->where('id', $conversationId)
                ->where('user_id', $resident->id)
                ->where('matter_id', $matter->id)
                ->exists();

            abort_unless($conversationBelongsToMatter, 404);
        }

        Log::info('AI 事项答疑开始', [
            'matter_id' => $matter->id,
            'resident_id' => $resident->id,
            'continuing' => $conversationId !== null,
        ]);

        $stream = ($conversationId !== null
            ? $agent->continue($conversationId, as: $resident)
            : $agent->forUser($resident)
        )->stream($validated['question']);

        return response()->stream(function () use ($stream, $matter, $resident) {
            try {
                foreach ($stream as $event) {
                    if ($event instanceof TextDelta && $event->delta !== '') {
                        yield $this->frame(['delta' => $event->delta]);

                        continue;
                    }

                    if ($event instanceof ProviderToolEvent
                        && $event->type === 'server_tool_use'
                        && $event->status === 'completed') {
                        $query = (string) ($event->data['input']['query'] ?? '');
                        if ($query !== '') {
                            Log::debug('AI 事项答疑触发联网检索', [
                                'matter_id' => $matter->id,
                                'resident_id' => $resident->id,
                                'query' => $query,
                            ]);
                            yield $this->frame(['searching' => $query]);
                        }

                        continue;
                    }

                    if ($event instanceof Citation) {
                        $citation = $event->citation;
                        yield $this->frame(['source' => [
                            'title' => $citation->title ?? '',
                            'url' => $citation->url ?? '',
                        ]]);
                    }
                }
            } catch (Throwable $e) {
                Log::warning('AI 事项答疑流式中断', [
                    'matter_id' => $matter->id,
                    'resident_id' => $resident->id,
                    'exception' => $e,
                ]);
                yield $this->frame(['error' => 'AI 暂时不可用，请稍后再试']);

                return;
            }

            $this->bindConversationToMatter($stream->conversationId, $resident->id, $matter->id);

            Log::debug('AI 事项答疑完成', [
                'matter_id' => $matter->id,
                'resident_id' => $resident->id,
                'conversation_id' => $stream->conversationId,
            ]);
            yield $this->frame([
                'done' => true,
                'conversation_id' => $stream->conversationId,
            ]);
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function frame(array $payload): string
    {
        return 'data: '.json_encode($payload, JSON_UNESCAPED_UNICODE)."\n\n";
    }

    private function bindConversationToMatter(string $conversationId, int $residentId, int $matterId): void
    {
        DB::table(config('ai.conversations.tables.conversations', 'agent_conversations'))
            ->where('id', $conversationId)
            ->where('user_id', $residentId)
            ->update(['matter_id' => $matterId]);
    }
}
