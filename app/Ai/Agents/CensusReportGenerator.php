<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

#[Timeout(240)]
class CensusReportGenerator implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
你是社区问卷的分析顾问。根据问卷的标题、目的、题目定义和用户的答案，生成一份简洁的中文个人报告，帮用户理清自己的选择、重点和接下来要确认的事。

要求：
- 顺着这份问卷本身的主题来写，用与它匹配的表达。
- 只依据用户填写的内容；能确定的讲确定，拿不准的归入「待确认」。
- 分清楚：已经确定的、偏好倾向、想守住的底线、仍待确认的。
- 答案之间有冲突时直接点出来。
- 语言简洁、具体。
PROMPT;
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'headline' => $schema->string()->required(),
            'overview' => $schema->string()->required(),
            'priorities' => $schema->array()->items(
                $schema->object(fn (JsonSchema $schema): array => [
                    'title' => $schema->string()->required(),
                    'reason' => $schema->string()->required(),
                    'action' => $schema->string()->required(),
                ]),
            )->required(),
            'decisions' => $schema->array()->items(
                $schema->object(fn (JsonSchema $schema): array => [
                    'topic' => $schema->string()->required(),
                    'choice' => $schema->string()->required(),
                    'meaning' => $schema->string()->required(),
                ]),
            )->required(),
            'open_questions' => $schema->array()->items($schema->string())->required(),
            'risks' => $schema->array()->items($schema->string())->required(),
        ];
    }
}
