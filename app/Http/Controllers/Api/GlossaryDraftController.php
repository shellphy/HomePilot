<?php

namespace App\Http\Controllers\Api;

use App\Ai\Agents\GlossaryDrafter;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * 「买前必懂」AI 改写：发起人先手填一段术语说明，这里把它改写得更清楚后回填同一个输入框。
 * AI 只改写，团长/管理员把关后才随事项提交——草稿不直接落库。
 */
class GlossaryDraftController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'term' => ['required', 'string', 'max:30'],
            'draft' => ['required', 'string', 'max:500'],
            'category' => ['sometimes', 'nullable', 'string', 'max:30'],
        ]);

        $category = $validated['category'] ?? '';

        Log::info('AI 术语说明改写开始', [
            'term' => $validated['term'],
            'category' => $category,
            'draft_len' => mb_strlen($validated['draft']),
        ]);

        try {
            $rewritten = (new GlossaryDrafter)->prompt(
                ($category !== '' ? "团购品类：{$category}\n" : '')
                ."术语：{$validated['term']}\n"
                ."发起人草稿：{$validated['draft']}",
            )->text;
        } catch (Throwable $e) {
            Log::warning('AI 术语说明改写失败（联网检索或模型异常）', [
                'term' => $validated['term'],
                'category' => $category,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
            abort(502, 'AI 改写暂时不可用，请稍后再试或直接使用你填写的内容');
        }

        $explain = trim((string) $rewritten);
        Log::debug('AI 术语说明改写完成', [
            'term' => $validated['term'],
            'explain_len' => mb_strlen($explain),
        ]);

        // 与 glossary 校验的 max:500 对齐，超长截断
        return response()->json(['data' => [
            'term' => $validated['term'],
            'explain' => Str::limit($explain, 497),
        ]]);
    }
}
