<?php

namespace App\Ai\Concerns;

use Laravel\Ai\Providers\Tools\WebSearch;

/**
 * 给 Agent 接入服务端联网检索：DeepSeek 通过 Anthropic 兼容端点在单次响应内执行
 * web search，无需客户端多步循环。搭配 config('ai') 里的 deepseek-anthropic provider 使用。
 */
trait SearchesWeb
{
    /**
     * @return iterable<int, WebSearch>
     */
    public function tools(): iterable
    {
        return [(new WebSearch)->max(3)];
    }

    /**
     * 结构化 agent 追加到 instructions 末尾。联网后 DeepSeek 会以 text 收尾、不再
     * 调用合成输出工具，因此必须要求模型最终只吐纯 JSON，交给 SDK 的 text→JSON 回退解析。
     * 非结构化 agent（如答疑）不要用它——那里要输出自然语言。
     */
    protected function structuredJsonDirective(): string
    {
        return "\n\n完成联网检索后，你的最后一条回复必须只包含一个符合所需字段结构的 JSON 对象；"
            .'不要输出该 JSON 之外的任何文字、说明或代码围栏。';
    }
}
