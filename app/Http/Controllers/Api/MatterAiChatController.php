<?php

namespace App\Http\Controllers\Api;

use App\Ai\Agents\MatterExplainer;
use App\Http\Controllers\Api\Concerns\ResolvesResident;
use App\Http\Controllers\Controller;
use App\Models\Matter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Ai\Streaming\Events\Citation;
use Laravel\Ai\Streaming\Events\ProviderToolEvent;
use Laravel\Ai\Streaming\Events\TextDelta;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * 业主侧 AI 答疑（多轮）：带着事项上下文回答，conversation_id 续聊。
 * AI 解释概念、不代表承诺；涉及商家具体承诺的引导去向团长/商家提问留档。
 *
 * 回答以 SSE（text/event-stream）逐字下发，让前端打字机式渲染、并支持中途停止：
 * 每个 `data:` 帧是一段 JSON——{delta} 增量文字、{error} 出错、
 * {done, conversation_id, remaining_today} 收尾（会话此刻才落库）。
 */
class MatterAiChatController extends Controller
{
    use ResolvesResident;

    /** 每人每天的提问上限：模型便宜，限额从宽，只防脚本刷接口。 */
    private const DAILY_LIMIT = 100;

    public function store(Request $request, Matter $matter): StreamedResponse
    {
        $resident = $this->resident($request);

        // 公示后人人可问；公示前只有发起人能问，方便自己边办边理清
        abort_unless($matter->is_approved || $matter->initiator_id === $resident->id, 404);
        abort_unless(in_array($matter->type, ['groupbuy', 'activity', 'census'], true), 422, '该事项不支持 AI 答疑');

        $validated = $request->validate([
            'question' => ['required', 'string', 'max:300'],
            'conversation_id' => ['sometimes', 'nullable', 'string', 'max:64'],
        ]);

        $rateKey = 'matter-ai-chat:'.$resident->id;
        if (RateLimiter::tooManyAttempts($rateKey, self::DAILY_LIMIT)) {
            abort(429, '今天的 AI 提问次数用完了，明天再来；急事可在本页向团长提问');
        }
        RateLimiter::hit($rateKey, 86400);

        $agent = new MatterExplainer($matter, $resident);
        $conversationId = $validated['conversation_id'] ?? null;

        Log::info('AI 事项答疑开始', [
            'matter_id' => $matter->id,
            'resident_id' => $resident->id,
            'continuing' => $conversationId !== null,
        ]);

        $stream = ($conversationId !== null
            ? $agent->continue($conversationId, as: $resident)
            : $agent->forUser($resident)
        )->stream($validated['question']);

        return response()->stream(function () use ($stream, $rateKey, $matter, $resident) {
            try {
                foreach ($stream as $event) {
                    if ($event instanceof TextDelta && $event->delta !== '') {
                        yield $this->frame(['delta' => $event->delta]);

                        continue;
                    }

                    // 服务端联网检索：确定搜索词时下发 {searching}（前端显示检索状态），
                    // 命中来源下发 {source}（附在答案下）。
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
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);
                yield $this->frame(['error' => 'AI 暂时不可用，请稍后再试']);

                return;
            }

            // 迭代结束后 RememberConversation 中间件才写库并回填 conversation_id
            $remaining = RateLimiter::remaining($rateKey, self::DAILY_LIMIT);
            Log::debug('AI 事项答疑完成', [
                'matter_id' => $matter->id,
                'resident_id' => $resident->id,
                'conversation_id' => $stream->conversationId,
                'remaining_today' => $remaining,
            ]);
            yield $this->frame([
                'done' => true,
                'conversation_id' => $stream->conversationId,
                'remaining_today' => $remaining,
            ]);
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no', // 让 nginx 等反代不缓冲，逐帧透传
        ]);
    }

    /**
     * 拼一帧 SSE 事件（Laravel 的流式响应会在每次 yield 后自动冲刷）。
     *
     * @param  array<string, mixed>  $payload
     */
    private function frame(array $payload): string
    {
        return 'data: '.json_encode($payload, JSON_UNESCAPED_UNICODE)."\n\n";
    }
}
