<?php

namespace Database\Seeders;

use App\Models\Matter;
use App\Models\MatterUpdate;
use App\Models\Record;
use App\Models\Resident;
use App\Settings\CommunitySettings;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * 本地联调数据：小区生活全景——公告、维权、活动、互助、团购五种事务 + 一批装修档案。
     * 装修只是其中一个板块；其余事务对应业主群里真实在聊的话题。
     */
    public function run(): void
    {
        $settings = app(CommunitySettings::class);

        // 管理员是被授权的成员（真机登录后 php artisan admin:grant 你的微信号或成员 ID）；种子里给老K
        $initiator = Resident::factory()->inUnit('3栋')->create(['nickname' => '老K', 'is_admin' => true]);

        $decoration = Matter::factory()->open()->for($initiator, 'initiator')->create([
            'category' => '装修公司',
            'title' => '「城建装饰」整装团购',
            'target_count' => 20,
            'payload' => [
                'pitch' => '我自己家就签的这家，合同、报价单、工地进度全部公开，大家随时来我家工地看。谈下来的条件对每一户一样。',
                'perk' => '满 20 户赠全屋水电升级（点位不限量）',
                'terms' => [
                    ['label' => '半包', 'value' => '618 元/㎡（门市 688）'],
                    ['label' => '全包', 'value' => '1150 元/㎡（门市 1280）'],
                    ['label' => '质保', 'value' => '隐蔽工程 10 年，写入合同'],
                ],
                'glossary' => [
                    ['term' => '半包 vs 全包', 'explain' => '半包=人工+辅料，主材自己买；全包=全含。差价大头在主材品牌，对比报价先看主材清单。'],
                    ['term' => '水电点位', 'explain' => '每个插座、开关、出水口算一个点位，按个收费最容易超预算——所以"点位不限量"是实在让利。'],
                    ['term' => '隐蔽工程质保', 'explain' => '封进墙里的水电和防水出问题维修最贵，质保年限必须写进合同。'],
                ],
            ],
        ]);
        Record::factory()->count(17)->for($decoration, 'matter')->create();
        MatterUpdate::factory()->for($decoration, 'matter')->create([
            'happened_on' => now()->subDays(7)->toDateString(),
            'content' => '团长家水电开槽完成，横平竖直验收通过',
        ]);
        MatterUpdate::factory()->for($decoration, 'matter')->create([
            'happened_on' => now()->subDays(19)->toDateString(),
            'content' => '团长家开工交底，报价单（脱敏）已公开',
        ]);

        $hvac = Matter::factory()->negotiating()->for($initiator, 'initiator')->create([
            'category' => '中央空调',
            'title' => '中央空调方案征集',
            'target_count' => 25,
            'payload' => [
                'pitch' => '',
                'perk' => '满 25 户总价再降 3%',
                'terms' => [['label' => '方案', 'value' => '大金 / 美的两套方案对比中']],
                'glossary' => [],
            ],
        ]);
        Record::factory()->count(23)->for($hvac, 'matter')->create();

        Matter::factory()->for($initiator, 'initiator')->create([
            'category' => '全屋定制',
            'title' => '全屋定制柜体 意向征集',
            'target_count' => 15,
            'payload' => ['pitch' => '', 'perk' => '', 'terms' => [], 'glossary' => []],
        ]);

        // ---- 公告：小区消息的权威沉淀 ----
        Matter::factory()->notice()->create([
            'title' => '本小程序公益运营说明',
            'payload' => [
                'body' => $settings->app_name.'由本小区业主公益运营：不代收任何款项，签约付款由业主直接对商家；商家给到的任何返点，全部转为参团业主让利，并在成团后随成交公示摊开。',
            ],
        ]);
        Matter::factory()->notice()->create([
            'title' => '学区消息：片区小学分校规划已公示',
            'payload' => [
                'body' => '区教育局官网已挂出片区小学分校的规划公示，选址在小区东侧地块，办学规模 36 个班。公示期到月底，原文链接已发业主群。是否划入我们小区学区以教育局最终文件为准，有新消息会更新在这里。',
            ],
        ]);
        Matter::factory()->notice()->create([
            'title' => '物业前期服务费标准（转自开发商）',
            'payload' => [
                'body' => '开发商公布前期物业为集团自营物业，收费标准 2.8 元/㎡/月，含公区保洁、绿化和秩序维护；车位管理费另计。交房时会随收房通知一并寄送，大家先了解，有异议可以在维权板块联名反馈。',
            ],
        ]);

        // ---- 维权：对开发商/物业的集体发声 ----
        $rights = Matter::factory()->rights()->for($initiator, 'initiator')->create([
            'title' => '地下车位定价过高，联名要求公开成本',
            'target_count' => 100,
            'payload' => [
                'pitch' => '销售口径车位 15.8 万一个，周边同类小区普遍 10~12 万。凑满 100 户联名，正式向开发商递交问询函，要求公开车位定价依据并给出团购价。',
            ],
        ]);
        Record::factory()->count(47)->for($rights, 'matter')->create();

        $inspection = Matter::factory()->rights()->for($initiator, 'initiator')->create([
            'state' => 'negotiating',
            'title' => '精装交付标准与样板间不符，集体交涉中',
            'target_count' => 50,
            'payload' => [
                'pitch' => '有邻居从工地照片发现门槛石、卫浴五金与样板间标注品牌不一致。已凑齐 50 户联名并递交开发商，等待书面答复，进展会更新在本页。',
            ],
        ]);
        Record::factory()->count(52)->for($inspection, 'matter')->create();
        MatterUpdate::factory()->for($inspection, 'matter')->create([
            'happened_on' => now()->subDays(3)->toDateString(),
            'content' => '开发商客服已签收联名函，承诺 15 个工作日内书面答复',
        ]);

        // ---- 活动：邻里关系的孵化器 ----
        $activity = Matter::factory()->activity()->for($initiator, 'initiator')->create([
            'title' => '周六建材市场组团踩点（第二期）',
            'target_count' => 15,
            'payload' => [
                'pitch' => '这周六上午去富森美建材市场，主看门窗和全屋定制，有经验的邻居带队讲怎么看材料。上期去了 8 家人，收获很大。地铁站集合，报名后拉小群。',
            ],
        ]);
        Record::factory()->count(9)->for($activity, 'matter')->create();

        // ---- 互助：轻量的邻里协作 ----
        $aid = Matter::factory()->aid()->for($initiator, 'initiator')->create([
            'title' => '拼车去工地看进度（本周日上午）',
            'payload' => [
                'pitch' => '本周日上午去项目工地看施工进度，我开车有 3 个空位，住得近的邻居可以拼车，油费不用给，人齐出发。',
            ],
        ]);
        Record::factory()->count(3)->for($aid, 'matter')->create();

        // ---- 征集：装修意向摸底（问卷 schema 在事务 payload 里，登记=对它的表态）----
        $census = Matter::factory()->create([
            'type' => 'census',
            'initiator_id' => null,
            'state' => 'open',
            'category' => '装修',
            'title' => '装修意向摸底 · 全小区征集中',
            'target_count' => 0,
            'payload' => [
                'pitch' => '2 分钟登记你家的户型、装修方式和感兴趣的品类。登记越多，和商家谈判的筹码越足；数据匿名聚合公示，明细仅管理员可见。',
                'collects_contact' => true,
                'modules' => array_merge([[
                    'key' => 'basic',
                    'title' => '基础登记',
                    'intro' => '约 2 分钟 · 明细仅管理员可见，对外只展示汇总统计',
                    'questions' => [
                        ['key' => 'layout', 'text' => '你家是哪个户型？', 'type' => 'single', 'options' => $settings->layouts, 'required' => true],
                        ['key' => 'decoration_mode', 'text' => '打算怎么装？', 'type' => 'single', 'options' => $settings->decoration_modes, 'required' => true],
                        ['key' => 'interests', 'text' => '对哪些团购感兴趣？', 'type' => 'multi', 'options' => $settings->categories, 'required' => true],
                    ],
                ]], $this->surveyModules()),
            ],
        ]);
        Record::factory()->count(46)->censusAnswers()->create(['matter_id' => $census->id]);
    }

    /**
     * 装修意向摸底的进阶模块题库（种子内容；上线后在小程序「小区管理」的问卷编辑里维护）。
     * 原则：生活方式优先、不用行业术语、每题都要能转化成团购谈判或内容规划的依据。
     *
     * @return array<int, array<string, mixed>>
     */
    private function surveyModules(): array
    {
        return [
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
        ];
    }
}
