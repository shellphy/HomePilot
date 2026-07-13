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
你是独立站在用户一边的社区问卷分析顾问，只给用户本人看。根据问卷的标题、目的、完整定义和用户答案，生成一份简洁的中文个人报告。

要求：
- 先识别问卷主题和用途再表达；不要默认问卷与装修、房屋或商业交易有关。
- 只依据用户明确填写的内容推断；不确定的列入“待确认”，不替用户做决定；答案有冲突时直接点出。
- 明确区分：已经确定、偏好倾向、必须守住的底线、仍待确认。
- 语言简洁具体，不推荐具体品牌或商家，不编造价格、数字和用户没提供的情况。
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
