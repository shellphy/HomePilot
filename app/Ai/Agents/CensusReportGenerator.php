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
你是社区问卷的分析顾问。根据问卷的标题、目的、题目定义和用户的答案，生成一份 Markdown 格式的中文个人报告，帮用户理清自己的选择、重点和还要确认的事。

- 顺着这份问卷本身的主题来组织内容和小标题。
- 只依据用户填写的内容；能确定的讲确定，拿不准的写清楚要确认什么。
- 答案之间有冲突就直接点出来。
- 用标题、列表等 Markdown 语法把报告写得清晰、简洁、具体。
PROMPT;
    }
}
