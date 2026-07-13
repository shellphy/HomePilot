<?php

namespace App\Ai\Agents;

use App\Ai\Concerns\SearchesWeb;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * 「买前必懂」改写器：发起人自己先手填一段术语说明，AI 只把它改写得更清楚、口语、好懂。
 * 产出交发起人把关后才随事项发布。接入联网核对事实。
 */
#[Provider('deepseek-anthropic')]
#[Timeout(90)]
class GlossaryDrafter implements Agent, HasTools
{
    use Promptable, SearchesWeb;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
发起人写好了一段给居民看的术语说明草稿，你把它改得更清楚、更口语、更好懂，忠于原意。

- 保留草稿的信息和结论；说不清的地方联网核对常识后补一句。
- 面向不熟悉该领域的居民，用大白话讲。
- 对品牌、商家保持中立；拿不准的写「以相关方书面确认为准」。
- 直接给出改写后的一段正文。
PROMPT;
    }
}
