<?php

namespace App\Http\Controllers\Api;

use App\Ai\Agents\GlossaryDrafter;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;

/**
 * 「买前必懂」AI 起草：输入术语（带品类上下文），返回三段式草稿回填表单。
 * AI 只起草，团长/管理员校订后才随事项提交——草稿不直接落库。
 */
class GlossaryDraftController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'term' => ['required', 'string', 'max:30'],
            'category' => ['sometimes', 'nullable', 'string', 'max:30'],
        ]);

        $category = $validated['category'] ?? '';

        try {
            $draft = (new GlossaryDrafter)->prompt(
                ($category !== '' ? "团购品类：{$category}\n" : '')."术语：{$validated['term']}",
            );
        } catch (Throwable) {
            abort(502, 'AI 起草暂时不可用，请稍后再试或手动填写');
        }

        // 结构化 agent 实际返回 StructuredAgentResponse（prompt() 声明的是父类）
        abort_unless($draft instanceof StructuredAgentResponse, 502, 'AI 起草暂时不可用，请稍后再试或手动填写');

        // 与 glossary 校验的 max:300 对齐，超长草稿截断而不是让提交时报错
        return response()->json(['data' => [
            'term' => $validated['term'],
            'explain' => Str::limit((string) $draft['explain'], 297),
            'judge' => Str::limit((string) $draft['judge'], 297),
            'caution' => Str::limit((string) $draft['caution'], 297),
        ]]);
    }
}
