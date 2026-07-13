<?php

namespace App\Ai\Agents;

use App\Ai\Concerns\SearchesWeb;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

#[Provider('deepseek-anthropic')]
#[Timeout(240)]
class CensusReportGenerator implements Agent, HasTools
{
    use Promptable, SearchesWeb;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
你是社区问卷的分析顾问。根据问卷的标题、目的、题目定义和用户的答案，生成一份 Markdown 格式的中文个人报告。

- 先判断这份问卷是做什么的，再决定报告讲什么、怎么组织，用贴合它的小标题。
- 只依据用户填写的内容；涉及外部时效信息（政策、行情、规范）可联网查证后再写。
- 用标题、列表等 Markdown 语法写得清晰、简洁、具体。
PROMPT;
    }
}
