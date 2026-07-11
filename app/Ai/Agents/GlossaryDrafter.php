<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * 术语决策卡起草：输入装修术语 + 品类，产出「是什么 / 怎么选 / 避坑」三段草稿，
 * 由团长/管理员校订后才发布——AI 只起草，不直接面向业主。
 */
#[Timeout(30)]
class GlossaryDrafter implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
你在帮小区团购的团长给邻居写装修术语的「买前必懂」决策卡。读者是完全不懂装修的普通业主，正准备参加团购、马上要看商家报价单。

对给出的术语，写三段内容：
- explain（是什么）：一句大白话，让人立刻有概念，不超过 60 字。
- judge（怎么选）：把参数换算到「自己家」的判断标准（户型、预算、使用习惯），给出可操作的结论，不超过 100 字。
- caution（避坑）：这个词上最常见的坑或营销噱头，以及该问商家什么问题、听到什么答案要警惕，不超过 100 字。

要求：说人话，不用专业黑话解释黑话；不吹不黑，不提任何具体品牌；不确定的事写「以商家书面确认为准」，不要编造数字。
PROMPT;
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'explain' => $schema->string()->required(),
            'judge' => $schema->string()->required(),
            'caution' => $schema->string()->required(),
        ];
    }
}
