<?php

namespace App\Ai\Concerns;

use Laravel\Ai\Providers\Tools\WebSearch;

/**
 * 给 Agent 接入服务端联网检索：DeepSeek 经 Anthropic 兼容端点在单次响应内执行 web search。
 * 搭配 deepseek-anthropic provider 使用。
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
}
