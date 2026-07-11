<?php

namespace App\Ai\Agents;

use App\Models\Matter;
use App\Settings\CommunitySettings;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * 业主侧 AI 答疑：面向正在犹豫要不要参加某个团购/活动/征集的业主，
 * 带着本事项的条款、买前必懂和小区硬条件回答，支持多轮追问——
 * 这份上下文正是业主去通用 AI（ChatGPT/豆包）问不到的部分。
 */
#[Timeout(30)]
class MatterExplainer implements Agent, Conversational
{
    use Promptable, RemembersConversations;

    public function __construct(public Matter $matter) {}

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
你是小区团购小程序里的 AI 顾问，帮完全不懂装修的业主看懂下面这件小区里正在张罗的事，用大白话回答业主的疑问。

规则：
- 简短回答（默认 150 字以内），先给结论再给理由，业主追问时再展开。
- 优先结合下面的背景资料回答；把参数换算到业主自己家的情况来讲。
- 不评价、不推荐、不贬低任何具体品牌或商家。
- 凡涉及本次商家的具体承诺（保修年限、售后范围、赠品、最终价格），明确提醒以商家书面确认为准，建议业主在事项页向团长/商家提问留档。
- 拿不准的直接说不确定，不编数字。
- 与本小区、装修、这件事无关的问题，礼貌说明你只聊这件事相关的内容。

PROMPT.$this->matterContext();
    }

    /**
     * 事项上下文（业主在通用 AI 那里拿不到的部分）：小区硬条件 + 本事项的全部公示信息。
     */
    private function matterContext(): string
    {
        $settings = app(CommunitySettings::class);
        $type = $this->matter->typeDef();

        $lines = [
            '',
            '【背景资料（业主看不到这段原文，回答时直接使用）】',
            "小区：{$settings->name}",
        ];

        if ($settings->ai_context !== '') {
            $lines[] = "小区硬条件：{$settings->ai_context}";
        }

        $lines[] = "事项：{$type->label()}「{$this->matter->title}」"
            .($this->matter->category !== '' ? "，品类：{$this->matter->category}" : '')
            ."，当前阶段：{$type->stateLabel($this->matter->state)}";

        if ($this->matter->payloadValue('needs_survey')) {
            $lines[] = '这是按户出方案的团购：报名后商家逐户沟通需求，每户的方案和报价单独谈。';
        }

        if (($pitch = (string) $this->matter->payloadValue('pitch', '')) !== '') {
            $lines[] = "发起人的话：{$pitch}";
        }

        if (($perk = (string) $this->matter->payloadValue('perk', '')) !== '') {
            $lines[] = "阶梯优惠：{$perk}";
        }

        foreach ($this->matter->payloadList('terms') as $term) {
            $lines[] = "条款 · {$term['label']}：{$term['value']}";
        }

        foreach ($this->matter->payloadList('glossary') as $entry) {
            $line = "买前必懂 · {$entry['term']}：{$entry['explain']}";
            if (($entry['judge'] ?? '') !== '') {
                $line .= "；怎么选：{$entry['judge']}";
            }
            if (($entry['caution'] ?? '') !== '') {
                $line .= "；避坑：{$entry['caution']}";
            }
            $lines[] = $line;
        }

        if ($this->matter->initiatorParty) {
            $lines[] = "发起方：{$this->matter->initiatorParty->typeLabel()}「{$this->matter->initiatorParty->name}」"
                .($this->matter->initiatorParty->is_listed ? '（已认证）' : '（未认证）');
        }

        return implode("\n", $lines);
    }
}
