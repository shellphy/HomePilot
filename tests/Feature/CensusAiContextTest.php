<?php

use App\Ai\Agents\MatterExplainer;
use App\Models\Matter;
use App\Models\Resident;
use App\Models\Stance;
use App\Settings\CommunitySettings;

/**
 * 用带答案的征集构造 explainer，断言指令里带上了征集专属上下文。
 */
function censusWithAnswers(): array
{
    $census = Matter::factory()->create([
        'type' => 'census',
        'state' => 'open',
        'title' => '定制柜摸底',
        'payload' => [
            'purpose' => '开团前先摸清大家想装什么',
            'modules' => [[
                'key' => 'm1',
                'title' => '柜体',
                'intro' => '先确定柜体材料，再比较价格。',
                'questions' => [
                    [
                        'key' => 'q1',
                        'text' => '柜体倾向哪种板材？',
                        'type' => 'single',
                        'note' => '板材影响环保、防潮和预算。',
                        'options' => ['颗粒板', '多层实木'],
                        'option_notes' => ['便宜，环保看等级', '贵约三成，更防潮'],
                    ],
                    [
                        'key' => 'q2',
                        'text' => '还有什么想说的？',
                        'type' => 'text',
                    ],
                ],
            ]],
        ],
    ]);

    $asker = Resident::factory()->create();
    $census->stances()->create([
        'resident_id' => $asker->id,
        'mode' => Stance::MODE_REGISTER,
        'payload' => [
            'answers' => ['q1' => '多层实木', 'q2' => '希望环保达标'],
            'ai_report' => "## 我的问卷总结\n\n你重视环保与防潮。",
        ],
    ]);

    // 另外两户也登记，让「多数选择」有可聚合的数据
    foreach (['颗粒板', '颗粒板'] as $choice) {
        $census->stances()->create([
            'resident_id' => Resident::factory()->create()->id,
            'mode' => Stance::MODE_REGISTER,
            'payload' => ['answers' => ['q1' => $choice]],
        ]);
    }

    return [$census, $asker];
}

test('census ai context carries purpose, questions, option notes, my answer and top choice', function () {
    [$census, $asker] = censusWithAnswers();

    $instructions = (string) (new MatterExplainer($census, $asker))->instructions();

    expect($instructions)
        ->toContain('不要顺着明显错误继续推导')
        ->toContain('开团前先摸清大家想装什么') // 发起目的
        ->toContain('模块：柜体')
        ->toContain('先确定柜体材料，再比较价格。')
        ->toContain('柜体倾向哪种板材？')       // 题面
        ->toContain('板材影响环保、防潮和预算。')
        ->toContain('多层实木｜贵约三成，更防潮') // 选项 + 解释
        ->toContain('柜体倾向哪种板材？→多层实木') // 提问业主自己的选择（换算成题面文字）
        ->toContain('希望环保达标')               // 我填空题的答案
        ->toContain('重视环保与防潮')             // 已生成报告可继续追问
        ->toContain('多数选「颗粒板」（2 人）');   // 匿名聚合的多数选择
});

test('census ai context uses draft answers passed with the question over saved ones', function () {
    [$census, $asker] = censusWithAnswers();

    // 存库里 q1 = 多层实木；本地未保存改成颗粒板，AI 应看到最新的草稿选择
    $instructions = (string) (new MatterExplainer($census, $asker, ['q1' => '颗粒板']))->instructions();

    expect($instructions)
        ->toContain('柜体倾向哪种板材？→颗粒板')
        ->not->toContain('柜体倾向哪种板材？→多层实木');
});

test('census ai context shows draft answers even without a saved registration', function () {
    [$census] = censusWithAnswers();

    $fresh = Resident::factory()->create();
    $instructions = (string) (new MatterExplainer($census, $fresh, ['q1' => '多层实木']))->instructions();

    expect($instructions)->toContain('柜体倾向哪种板材？→多层实木');
});

test('census ai context omits my registration for a resident who has not answered', function () {
    [$census] = censusWithAnswers();

    $freshResident = Resident::factory()->create();
    $instructions = (string) (new MatterExplainer($census, $freshResident))->instructions();

    // 没登记就不注入「我的选择」，但问卷题目与多数选择照常在
    expect($instructions)
        ->toContain('柜体倾向哪种板材？')
        ->not->toContain('提问业主的登记');
});

test('census ai context includes every question without truncation', function () {
    $questions = collect(range(1, 28))->map(fn (int $number): array => [
        'key' => "q{$number}",
        'text' => "第 {$number} 道硬装题",
        'type' => 'single',
        'note' => "第 {$number} 道说明",
        'options' => ['需要', '不需要'],
    ])->all();

    $census = Matter::factory()->create([
        'type' => 'census',
        'payload' => ['modules' => [[
            'key' => 'hard_finish',
            'title' => '硬装',
            'questions' => $questions,
        ]]],
    ]);

    $instructions = (string) (new MatterExplainer($census))->instructions();

    expect($instructions)
        ->toContain('第 1 道硬装题')
        ->toContain('第 28 道硬装题')
        ->toContain('第 28 道说明');
});

test('renovation matter injects community hard conditions', function () {
    $settings = app(CommunitySettings::class);
    $settings->ai_context = '每户只有一个外机位';
    $settings->save();

    $matter = Matter::factory()->create(['type' => 'groupbuy', 'category' => '中央空调']);

    expect((string) (new MatterExplainer($matter))->instructions())
        ->toContain('小区硬条件：每户只有一个外机位');
});

test('non-renovation matter omits装修 hard conditions', function () {
    $settings = app(CommunitySettings::class);
    $settings->ai_context = '每户只有一个外机位';
    $settings->save();

    // 活动事项（品类不在装修白名单）不该拿到装修硬条件
    $matter = Matter::factory()->create(['type' => 'activity', 'category' => '亲子活动']);

    expect((string) (new MatterExplainer($matter))->instructions())
        ->not->toContain('小区硬条件');
});

test('groupbuy ai context is unaffected by the census branch', function () {
    $groupbuy = Matter::factory()->create([
        'title' => '中央空调团购',
        'payload' => ['pitch' => '我自己家也装这套', 'purpose' => '不应出现'],
    ]);

    $instructions = (string) (new MatterExplainer($groupbuy))->instructions();

    expect($instructions)
        ->toContain('我自己家也装这套')
        ->not->toContain('发起目的'); // census 分支不碰其它类型
});
