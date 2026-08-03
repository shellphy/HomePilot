<?php

namespace App\Ai\Agents;

use App\Ai\Concerns\SearchesWeb;
use App\Matters\MatterTypeRegistry;
use App\Models\Matter;
use App\Models\Resident;
use App\Models\Stance;
use App\Settings\CommunitySettings;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * 居民侧 AI 答疑：面向正在了解某个团购/活动/征集的居民，
 * 带着本事项的条款、买前必懂和小区硬条件回答，也可解答小区生活及相关政策问题。
 * 叠加联网检索：本事项上下文之外的政策、规范和行情等时效信息也能查证再答。
 */
#[Provider('deepseek-anthropic')]
#[Timeout(90)]
class MatterExplainer implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations, SearchesWeb;

    /**
     * 只有这些装修相关品类的事项才注入小区硬条件（ai_context）。
     *
     * @var list<string>
     */
    private const RENOVATION_CATEGORIES = ['装修', '装修公司', '中央空调', '全屋定制', '软装', '地暖', '门窗'];

    /**
     * @param  array<string, mixed>|null  $draftAnswers  问 AI 时随请求带上的当前（可能未保存）答案，覆盖已存答案
     */
    public function __construct(public Matter $matter, public ?Resident $asker = null, public ?array $draftAnswers = null) {}

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        $name = app(CommunitySettings::class)->name;
        $today = now()->format('Y 年 n 月 j 日');

        return <<<PROMPT
你是「{$name}」小程序里的 AI 顾问。你既要帮助居民看懂当前社区事项，也可以回答与小区生活、住房装修、物业治理、邻里公共事务及相关政策、法规、规范或办事流程有关的问题；问题不必出现在当前事项或问卷题目中。今天是 {$today}，涉及行情、政策等时效信息以此为准、联网查证后再答，别按训练时的年份推断。

规则：
- 简短回答（默认 150 字以内），先给结论再给理由，居民追问时再展开。
- 居民问某道问卷题时，先解释这道题为什么要问、会影响什么，再结合已选答案和事项背景给出建议。
- 问卷题目和选项不一定正确；不要顺着明显错误继续推导，先指出并纠正，拿不准时联网查证。
- 信息不足以判断时，只追问一个最影响选择的关键问题。
- 区分“必须遵守的安全 / 规范底线”和“可以按预算偏好选择的方案”。
- 当前事项或问卷相关问题，优先结合下面的背景资料回答；其他小区生活或相关政策问题，按用户实际问题正常回答。
- 政策、法规、规范和办事流程应优先联网查证，说明适用地区和时间，并提醒以主管部门最新公开信息为准。
- 对具体品牌、商家、相关方保持中立，不替居民做选择。
- 凡涉及具体承诺、费用、服务范围或执行安排，明确提醒以事项公示或相关方书面确认为准，建议居民在事项页提问留档。
- 拿不准的直接说不确定。
- 与小区生活、业主共同事务及其相关政策完全无关的问题，礼貌说明你的服务范围。

PROMPT.$this->matterContext();
    }

    /**
     * 事项上下文：小区硬条件 + 本事项的全部公示信息。
     */
    private function matterContext(): string
    {
        $settings = app(CommunitySettings::class);
        $type = MatterTypeRegistry::for($this->matter->type);

        $lines = [
            '',
            '【背景资料（回答时直接使用）】',
            "小区：{$settings->name}",
        ];

        if ($settings->ai_context !== '' && in_array($this->matter->category, self::RENOVATION_CATEGORIES, true)) {
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
            $lines[] = '这是逐人报价的团购：报名后发起方单独和每位报名者沟通需求，各自的方案和报价单独谈。';
        }

        if ($this->matter->body !== '') {
            $lines[] = "发起人的话：{$this->matter->body}";
        }

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
            $lines[] = "买前必懂 · {$entry['term']}：{$entry['explain']}";
        }

        if ($this->matter->initiatorParty) {
            $lines[] = "发起方：{$this->matter->initiatorParty->typeLabel()}「{$this->matter->initiatorParty->name}」"
                .($this->matter->initiatorParty->is_listed ? '（已核验）' : '（未核验）');
        }

        return implode("\n", $lines);
    }

    /**
     * 征集专属上下文：发起目的、问卷题目、提问业主的登记、各题多数选择。
     *
     * @return array<int, string>
     */
    private function censusContextLines(): array
    {
        $lines = [];

        if (($purpose = trim((string) $this->matter->payloadValue('purpose', ''))) !== '') {
            $lines[] = "发起目的：{$purpose}";
        }

        /** @var array<string, array{text: string, type: string, options: array<int, string>}> $questionMap */
        $questionMap = [];
        $questionLines = [];

        foreach ($this->matter->payloadList('modules') as $module) {
            $moduleTitle = trim((string) ($module['title'] ?? ''));
            if ($moduleTitle !== '') {
                $questionLines[] = "模块：{$moduleTitle}";
            }

            foreach ((array) ($module['questions'] ?? []) as $question) {
                $key = (string) ($question['key'] ?? '');
                $text = trim((string) ($question['text'] ?? ''));
                if ($key === '' || $text === '') {
                    continue;
                }

                $type = (string) ($question['type'] ?? 'single');
                $options = array_values(array_map('strval', (array) ($question['options'] ?? [])));
                $questionMap[$key] = ['text' => $text, 'type' => $type, 'options' => $options];

                if ($type === 'text') {
                    $questionLines[] = "题目：{$text}（填空题）";

                    continue;
                }

                $questionLine = "题目：{$text}";
                if ($options !== []) {
                    $questionLine .= '；选项：'.implode(' / ', $options);
                }
                $questionLines[] = $questionLine;
            }
        }

        if ($questionLines !== []) {
            $lines[] = '完整问卷结构与选项：';
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

            $reportStance = $this->matter->stances()
                ->where('mode', Stance::MODE_REGISTER)
                ->where('resident_id', $this->asker->id)
                ->first();
            $report = $reportStance?->payload['ai_report'] ?? null;
            if (is_string($report) && $report !== '') {
                $lines[] = '已生成的个人问卷报告：'.$report;
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
     * @param  array<string, array{text: string, type: string, options: array<int, string>}>  $questionMap
     * @return array<int, string>
     */
    private function myRegistrationLines(array $questionMap): array
    {
        $stance = $this->matter->stances()
            ->where('mode', Stance::MODE_REGISTER)
            ->where('resident_id', $this->asker->id)
            ->first();

        // 未保存的本地答案（问 AI 时随请求带上）覆盖已存答案，AI 才看得到屏幕上的实时选择
        $stored = $stance?->payload['answers'] ?? [];
        $answers = array_merge(is_array($stored) ? $stored : [], $this->draftAnswers ?? []);
        if ($answers === []) {
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
     * @param  array<string, array{text: string, type: string, options: array<int, string>}>  $questionMap
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
