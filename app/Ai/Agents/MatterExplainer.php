<?php

namespace App\Ai\Agents;

use App\Matters\MatterTypeRegistry;
use App\Models\Matter;
use App\Models\Resident;
use App\Models\Stance;
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

    public function __construct(public Matter $matter, public ?Resident $asker = null) {}

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
        $type = MatterTypeRegistry::for($this->matter->type);

        $lines = [
            '',
            '【背景资料（业主看不到这段原文，回答时直接使用）】',
            "小区：{$settings->name}",
        ];

        if ($settings->ai_context !== '') {
            $lines[] = "小区硬条件：{$settings->ai_context}";
        }

        // 业主填过户型的话，「我家该怎么选」就能按他家的实际情况答
        $layout = $this->asker?->layout_label;
        if ($layout !== null && $layout !== '') {
            $lines[] = "提问业主的户型：{$layout}";
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

        // 征集：注入目的、问卷题目与选项解释、提问业主自己的登记、各题多数选择，
        // AI 才既能讲清某道题、又能基于「我家怎么答的」做整体分析。
        if ($this->matter->type === 'census') {
            foreach ($this->censusContextLines() as $censusLine) {
                $lines[] = $censusLine;
            }
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

    /**
     * 征集专属上下文：发起目的、问卷题目与选项解释、提问业主的登记、各题多数选择。
     *
     * @return array<int, string>
     */
    private function censusContextLines(): array
    {
        $lines = [];

        if (($purpose = trim((string) $this->matter->payloadValue('purpose', ''))) !== '') {
            $lines[] = "发起目的：{$purpose}";
        }

        /** @var array<string, array{text: string, type: string, options: array<int, string>, notes: array<int, string>}> $questionMap */
        $questionMap = [];
        $questionLines = [];
        // 题目多时只注入前若干题；答题现场问某道题会把题面写进 question，不受此截断影响
        $limit = 24;

        foreach ($this->matter->payloadList('modules') as $module) {
            foreach ((array) ($module['questions'] ?? []) as $question) {
                $key = (string) ($question['key'] ?? '');
                $text = trim((string) ($question['text'] ?? ''));
                if ($key === '' || $text === '') {
                    continue;
                }

                $type = (string) ($question['type'] ?? 'single');
                $options = array_values(array_map('strval', (array) ($question['options'] ?? [])));
                $notes = array_map('strval', (array) ($question['option_notes'] ?? []));
                $questionMap[$key] = ['text' => $text, 'type' => $type, 'options' => $options, 'notes' => $notes];

                if (count($questionLines) >= $limit) {
                    continue;
                }

                if ($type === 'text') {
                    $questionLines[] = "题目：{$text}（填空题）";

                    continue;
                }

                $pairs = [];
                foreach ($options as $i => $option) {
                    $note = trim((string) ($notes[$i] ?? ''));
                    $pairs[] = $note !== '' ? "{$option}｜{$note}" : $option;
                }

                $questionLines[] = "题目：{$text}"
                    .($pairs !== [] ? '；选项：'.implode(' / ', $pairs) : '');
            }
        }

        if ($questionLines !== []) {
            $lines[] = '问卷题目与选项：';
            foreach ($questionLines as $line) {
                $lines[] = "- {$line}";
            }
        }

        if ($this->asker !== null) {
            foreach ($this->myRegistrationLines($questionMap) as $i => $line) {
                if ($i === 0) {
                    $lines[] = '提问业主的登记（我的选择）：';
                }
                $lines[] = "- {$line}";
            }
        }

        foreach ($this->topChoiceLines($questionMap) as $i => $line) {
            if ($i === 0) {
                $lines[] = '各题多数选择（匿名聚合，帮你讲「大家多数怎么选」）：';
            }
            $lines[] = "- {$line}";
        }

        return $lines;
    }

    /**
     * 提问业主自己的登记：把答案的 key/选项换算成题面文字。
     *
     * @param  array<string, array{text: string, type: string, options: array<int, string>, notes: array<int, string>}>  $questionMap
     * @return array<int, string>
     */
    private function myRegistrationLines(array $questionMap): array
    {
        $stance = $this->matter->stances()
            ->where('mode', Stance::MODE_REGISTER)
            ->where('resident_id', $this->asker->id)
            ->first();

        $answers = $stance?->payload['answers'] ?? null;
        if (! is_array($answers)) {
            return [];
        }

        $lines = [];
        foreach ($answers as $key => $value) {
            $question = $questionMap[$key] ?? null;
            if ($question === null) {
                continue;
            }

            if ($question['type'] === 'text') {
                if (($text = trim((string) $value)) !== '') {
                    $lines[] = "{$question['text']}→{$text}";
                }

                continue;
            }

            $choices = array_filter(
                array_map('strval', (array) $value),
                fn (string $choice): bool => $choice !== '',
            );
            if ($choices !== []) {
                $lines[] = "{$question['text']}→".implode('、', $choices);
            }
        }

        return $lines;
    }

    /**
     * 各题多数选择：匿名聚合每道选择题的最高票选项。
     *
     * @param  array<string, array{text: string, type: string, options: array<int, string>, notes: array<int, string>}>  $questionMap
     * @return array<int, string>
     */
    private function topChoiceLines(array $questionMap): array
    {
        $registrations = $this->matter->stances()
            ->where('mode', Stance::MODE_REGISTER)
            ->get()
            ->map(fn (Stance $stance): array => is_array($stance->payload['answers'] ?? null)
                ? $stance->payload['answers']
                : []);

        if ($registrations->isEmpty()) {
            return [];
        }

        $lines = [];
        foreach ($questionMap as $key => $question) {
            if ($question['type'] === 'text') {
                continue;
            }

            $counts = [];
            foreach ($registrations as $answers) {
                foreach ((array) ($answers[$key] ?? null) as $choice) {
                    if (($choice = (string) $choice) !== '') {
                        $counts[$choice] = ($counts[$choice] ?? 0) + 1;
                    }
                }
            }

            if ($counts === []) {
                continue;
            }

            arsort($counts);
            $top = (string) array_key_first($counts);
            $lines[] = "{$question['text']}→多数选「{$top}」（{$counts[$top]} 人）";
        }

        return $lines;
    }
}
