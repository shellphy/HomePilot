<?php

return [

    /*
     * 本小区的户型列表（意向登记的选项，前端选项也从接口下发）。
     */
    'layouts' => [
        '107㎡',
        '130㎡',
        '154㎡',
    ],

    /*
     * 装修方式（小区同期交付，大家同期装修，不问开工时间）。
     */
    'decoration_modes' => [
        '全包（都交给装修公司）',
        '半包（主材自己买）',
        '清包（只请工人）',
        '还没定',
    ],

    /*
     * 团购品类（既是登记表的多选项，也是项目的开放品类；
     * 未来泛化为小区公共服务时在此扩展）。
     */
    'categories' => [
        '装修公司',
        '中央空调',
        '地暖',
        '全屋定制',
        '门窗',
        '软装家具',
        '瓷砖',
    ],

    /*
     * 小区总户数（进度地图的分母）。
     */
    'total_households' => (int) env('HOMEPILOT_TOTAL_HOUSEHOLDS', 312),

    /*
     * 进阶问卷（问卷体系一期）：模块化题库，服务端下发，改题不用发版小程序。
     * 原则：生活方式优先、不用行业术语、每题都要能转化成团购谈判或内容规划的依据。
     * type: single 单选 / multi 多选。答案以 {questionKey: value} 存入 registrations.answers。
     */
    'survey' => [
        [
            'key' => 'family',
            'title' => '家庭与居住',
            'intro' => '房子住给谁，决定了怎么装',
            'questions' => [
                ['key' => 'household_size', 'text' => '常住几口人？', 'type' => 'single', 'options' => ['1~2 人', '3 人', '4 人', '5 人及以上']],
                ['key' => 'new_baby', 'text' => '未来三年家里可能添小孩吗？', 'type' => 'single', 'options' => ['会', '可能', '不会']],
                ['key' => 'elderly', 'text' => '有老人常住，或以后可能同住吗？', 'type' => 'single', 'options' => ['常住', '以后可能', '不会']],
                ['key' => 'pets', 'text' => '养宠物吗？', 'type' => 'multi', 'options' => ['猫', '狗', '其他', '不养']],
            ],
        ],
        [
            'key' => 'lifestyle',
            'title' => '生活方式',
            'intro' => '装修本质上是给生活方式定型',
            'questions' => [
                ['key' => 'home_activities', 'text' => '在家最常做什么？', 'type' => 'multi', 'options' => ['做饭', '追剧观影', '打游戏', '阅读', '健身', '招待朋友', '居家办公']],
                ['key' => 'cooking', 'text' => '做饭频率？', 'type' => 'single', 'options' => ['几乎每天', '每周几次', '很少做饭']],
                ['key' => 'wardrobe', 'text' => '家里衣物多吗？', 'type' => 'single', 'options' => ['很多，总是塞不下', '一般', '比较少']],
            ],
        ],
        [
            'key' => 'storage',
            'title' => '收纳与家务',
            'intro' => '这些决定要不要多打柜子、留不留设备位',
            'questions' => [
                ['key' => 'housework', 'text' => '家务主要谁来做？', 'type' => 'single', 'options' => ['自己/家人', '父母帮忙', '请保洁']],
                ['key' => 'robot', 'text' => '扫地机器人、洗碗机这类设备？', 'type' => 'single', 'options' => ['必须安排', '看情况', '不太需要']],
                ['key' => 'stockpile', 'text' => '囤货习惯？', 'type' => 'single', 'options' => ['爱囤货', '正常水平', '极简主义']],
            ],
        ],
        [
            'key' => 'budget',
            'title' => '预算与担忧',
            'intro' => '匿名统计，只用于组织团购和安排内容',
            'questions' => [
                ['key' => 'budget_range', 'text' => '装修总预算大概？', 'type' => 'single', 'options' => ['15 万以内', '15~25 万', '25~40 万', '40 万以上', '还没概念']],
                ['key' => 'worries', 'text' => '装修最担心什么？', 'type' => 'multi', 'options' => ['超预算', '不懂行被坑', '施工质量', '材料环保', '工期拖延', '售后没人管']],
            ],
        ],
    ],
];
