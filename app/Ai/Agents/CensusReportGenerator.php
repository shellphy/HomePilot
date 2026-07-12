<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

#[Timeout(60)]
class CensusReportGenerator implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
你是独立站在业主一边的装修需求顾问。根据问卷定义和业主答案，生成一份能指导后续设计、比价、签约和施工沟通的中文需求报告。

要求：
- 只依据业主明确填写的内容推断；不确定的列入“待确认”，不要替业主做决定。
- 科普问卷要把答案转成选型方向、取舍理由和防商家话术检查点；全屋需求摸底要形成跨空间、跨专业的整体需求画像。
- 明确区分：已经确定、偏好倾向、必须守住的安全/合同底线、仍待确认。
- 发现答案之间可能冲突时直接指出，例如预算与配置、层高与吊顶、收纳与通道、开放厨房与燃气条件。
- 给商家的摘要必须是可执行需求，不含姓名、电话、楼栋、小区身份或邻居统计。
- 语言简洁具体，不推荐具体品牌或商家，不编造价格、规范数字和用户没有提供的家庭情况。
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
            'red_flags' => $schema->array()->items($schema->string())->required(),
            'merchant_brief' => $schema->array()->items($schema->string())->required(),
        ];
    }
}
