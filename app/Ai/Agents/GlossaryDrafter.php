<?php

namespace App\Ai\Agents;

use App\Ai\Concerns\SearchesWeb;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * 术语决策卡起草：输入事项术语 + 品类，产出「是什么 / 怎么选 / 避坑」三段草稿，
 * 由团长/管理员校订后才发布——AI 只起草，不直接面向业主。
 * 接入联网检索，让「怎么选 / 避坑」能参考实时行情与常见坑，不凭空编。
 */
#[Provider('deepseek-anthropic')]
#[Timeout(90)]
class GlossaryDrafter implements Agent, HasStructuredOutput, HasTools
{
    use Promptable, SearchesWeb;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
你在帮社区事项发起人给居民写术语的「参与前必懂」决策卡。读者不熟悉相关领域，正在判断是否参加以及如何选择。

对给出的术语，写三段内容：
- explain（是什么）：一句大白话，让人立刻有概念，不超过 60 字。
- judge（怎么选）：结合使用场景、数量、预算、时间或服务条件给出可操作的判断标准，不超过 100 字。
- caution（避坑）：说明最常见的误解、风险或营销噱头，以及该向相关方确认什么，不超过 100 字。

要求：根据输入术语和品类判断具体领域，不默认与装修有关；说人话，不用专业黑话解释黑话；不吹不黑，不提任何具体品牌；不确定的事写「以相关方书面确认为准」，不要编造数字。
PROMPT.$this->structuredJsonDirective();
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
