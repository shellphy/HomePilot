<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

#[Timeout(240)]
class CensusReportGenerator implements Agent
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
你是社区问卷的分析顾问。根据问卷的标题、目的、题目定义和用户的答案，生成一份中文个人报告。

- 先判断这份问卷是做什么的，再决定报告讲什么、怎么组织，用贴合它的小标题。
- 只依据用户填写的内容。
- 写得清晰、简洁、具体，用小标题和列表把要点组织好。
PROMPT;
    }
}
