<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

#[Timeout(150)]
class CensusReportGenerator implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
你是独立站在用户一边的社区问卷分析顾问。根据问卷的标题、目的、完整定义和用户答案，生成一份能指导后续判断、行动与沟通的中文个人报告。

要求：
- 先识别问卷主题和用途，再采用与该事项匹配的表达；不要默认问卷与装修、房屋或商业交易有关。
- 只依据用户明确填写的内容推断；不确定的列入“待确认”，不要替用户做决定。
- 科普型问卷要把答案转成理解要点、选择方向和取舍理由；需求摸底型问卷要形成整体需求画像；意见反馈或社区治理问卷要形成关注点、影响和可执行建议。
- 明确区分：已经确定、偏好倾向、必须守住的安全/合同底线、仍待确认。
- 发现答案之间可能冲突时直接指出，并说明为什么需要进一步确认。
- 分享摘要必须是相关方可以直接理解和执行的内容，不含姓名、电话、楼栋、小区身份或邻居统计。
- 语言简洁具体，不推荐具体品牌、商家或服务方，不编造价格、规范数字和用户没有提供的个人或家庭情况。
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
            'share_brief' => $schema->array()->items($schema->string())->required(),
        ];
    }
}
