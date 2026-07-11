<?php

namespace App\Http\Controllers\Api;

use App\Ai\Agents\MatterExplainer;
use App\Http\Controllers\Api\Concerns\ResolvesResident;
use App\Http\Controllers\Controller;
use App\Models\Matter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * 业主侧 AI 答疑（多轮）：带着事项上下文回答，conversation_id 续聊。
 * AI 解释概念、不代表承诺；涉及商家具体承诺的引导去向团长/商家提问留档。
 */
class MatterAiChatController extends Controller
{
    use ResolvesResident;

    /** 每人每天的提问上限：模型便宜，限额从宽，只防脚本刷接口。 */
    private const DAILY_LIMIT = 100;

    public function store(Request $request, Matter $matter): JsonResponse
    {
        $resident = $this->resident($request);

        abort_unless($matter->is_approved, 404);
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

        $agent = new MatterExplainer($matter);
        $conversationId = $validated['conversation_id'] ?? null;

        try {
            $response = ($conversationId !== null
                ? $agent->continue($conversationId, as: $resident)
                : $agent->forUser($resident)
            )->prompt($validated['question']);
        } catch (Throwable) {
            abort(502, 'AI 暂时不可用，请稍后再试');
        }

        return response()->json(['data' => [
            'answer' => $response->text,
            'conversation_id' => $response->conversationId,
            'remaining_today' => RateLimiter::remaining($rateKey, self::DAILY_LIMIT),
        ]]);
    }
}
