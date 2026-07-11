<?php

namespace Database\Seeders;

use App\Models\Matter;
use App\Models\MatterQuestion;
use App\Models\MatterUpdate;
use App\Models\Party;
use App\Models\Resident;
use App\Models\Stance;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /** 团长老K：管理员 + 大多数事项的发起人。 */
    private Resident $leader;

    /**
     * 本地联调数据：小区生活全景。
     * 覆盖团购全生命周期（方案型谈判中 / 标品接龙中 / 商家直供 / 已成团带评价 / 未成团收场 / 意向征集）、
     * 配套摸底问卷、署名调研、问答区、公告 / 维权 / 活动 / 互助。
     */
    public function run(): void
    {
        // 真机登录后 php artisan admin:grant 你的手机号或成员 ID 即可接管
        $this->leader = Resident::factory()->inUnit('3栋')->create(['nickname' => '老K', 'is_admin' => true]);

        $parties = $this->parties();
        $this->hvacGroupbuy();
        $this->decorationGroupbuy();
        $this->windowGroupbuy($parties['window'], $parties['windowStaff']);
        $this->floorHeatingDone();
        $this->tileAborted();
        $this->wardrobeSeeking();
        $this->notices();
        $this->rightsActions();
        $this->activityAndAid();
        $this->renovationCensus();
        $this->propertyCensus($parties['property']);
    }

    /**
     * 相关方：已认证商家进入公示名录；商家成员绑定身份后可发商家直供团、以商家名答疑。
     *
     * @return array{window: Party, windowStaff: Resident, property: Party}
     */
    private function parties(): array
    {
        Party::factory()->listed()->create([
            'name' => '青城中央空调',
            'category' => '中央空调',
            'intro' => '大金/美的授权经销，服务过周边 3 个小区',
            'description' => "门店：建材市场 A 区 12 号\n主营：家用中央空调设计安装\n服务过：江语城、麓湖上郡\n售后：整机 6 年，每年免费清洗一次",
        ]);

        $window = Party::factory()->listed()->create([
            'name' => '优居门窗',
            'category' => '门窗',
            'intro' => '断桥铝系统窗，工厂直营',
        ]);
        $windowStaff = Resident::factory()->create([
            'nickname' => '门窗小张',
            'affiliated_party_id' => $window->id,
            'last_party_id' => $window->id,
        ]);

        $property = Party::factory()->listed()->create([
            'type' => Party::TYPE_PROPERTY,
            'name' => '天青府物业服务中心',
            'category' => '',
            'intro' => '前期物业，交房后进驻',
        ]);

        Party::factory()->listed()->create([
            'type' => Party::TYPE_DEVELOPER,
            'name' => '青城置业',
            'category' => '',
            'intro' => '项目开发商，交付与维保对接方',
        ]);

        // 名录之外留一个待认证商家：管理端审核流程有活干
        Party::factory()->create([
            'name' => '匠心地暖',
            'category' => '地暖',
            'intro' => '申请入驻中',
        ]);

        return ['window' => $window, 'windowStaff' => $windowStaff, 'property' => $property];
    }

    /**
     * 中央空调 · 方案型团购（谈判中）：本小区只有一个外机位，这是最典型的非标品团。
     * 挂配套摸底问卷（结果给团长当谈判弹药），问答区有已答/热门未答。
     */
    private function hvacGroupbuy(): void
    {
        $hvac = Matter::factory()->negotiating()->for($this->leader, 'initiator')->create([
            'category' => '中央空调',
            'title' => '中央空调团购（按户出方案）',
            'target_count' => 25,
            'payload' => [
                'needs_survey' => true,
                'pitch' => '咱们小区每户只有一个外机位，基本都得走中央空调。我约了两家授权经销商来小区集中量房，每家单独出方案报价，条件按团购口径统一谈。',
                'perk' => '满 25 户总价再降 3%',
                'terms' => [
                    ['label' => '在谈方案', 'value' => '大金 / 美的两套方案对比中'],
                    ['label' => '报价口径', 'value' => '按户型出清单价，公示户均区间'],
                ],
                'glossary' => [
                    [
                        'term' => '1 拖 5',
                        'explain' => '一台外机带 5 个室内机，咱们只有一个外机位，装几个房间就看这个数字。',
                        'judge' => '看你有几个房间要装、会不会同时开。三房两厅通常 1 拖 4 就够；多一个内机，外机功率也要跟上，不是加台内机那么简单。',
                        'caution' => '问清外机匹数和同开率下的制冷量，只报「1 拖 5」不报外机型号的要警惕。',
                    ],
                    [
                        'term' => '双转子压缩机',
                        'explain' => '压缩机是空调的心脏，双转子指两个转子轮流做功，更省电也更静音。',
                        'judge' => '卧室对噪音敏感、常年开的家庭值得关注；偶尔开的话单双转子体感差别不大。',
                        'caution' => '让商家写明压缩机具体型号，答不上来或含糊其辞的要警惕。',
                    ],
                    [
                        'term' => '内机静压',
                        'explain' => '内机吹风的「劲」，决定风能不能送满整个房间。',
                        'judge' => '长条形客餐厅要选静压高的内机，方正小房间标准档就够。',
                        'caution' => '吊顶前一定确认内机位置和检修口，装完再改是大工程。',
                    ],
                ],
            ],
        ]);
        Stance::factory()->count(19)->intent()->for($hvac, 'matter')->create();
        Stance::factory()->count(4)->intent(false)->for($hvac, 'matter')->create();
        MatterUpdate::factory()->for($hvac, 'matter')->create([
            'happened_on' => now()->subDays(2)->toDateString(),
            'content' => '两家经销商本周六上午来小区集中量房，已登记意向的邻居留意电话',
        ]);

        // 配套摸底问卷：选项自带解释（答题即建概念），结果直接喂给谈判
        $survey = Matter::factory()->create([
            'type' => 'census',
            'initiator_id' => null,
            'state' => 'open',
            'category' => '中央空调',
            'title' => '中央空调需求摸底',
            'related_matter_id' => $hvac->id,
            'payload' => [
                'pitch' => '答 3 道题帮团长摸清全小区的需求口径，谈判更有底气；结果匿名聚合公示。',
                'modules' => [[
                    'key' => 'hvac',
                    'title' => '你家的情况',
                    'questions' => [
                        [
                            'key' => 'rooms', 'text' => '打算装几个房间？', 'type' => 'single', 'required' => true,
                            'options' => ['3 个（1 拖 3）', '4 个（1 拖 4）', '5 个（1 拖 5）', '还没想好'],
                            'option_notes' => ['两房或三房只装卧室客厅', '三房两厅最常见的配置', '四房或全屋覆盖', '选这个也是有效信息'],
                        ],
                        [
                            'key' => 'priority', 'text' => '更在意哪一样？', 'type' => 'single', 'required' => true,
                            'options' => ['静音', '省电', '价格', '品牌'],
                            'option_notes' => ['卧室睡眠敏感选这个，关注压缩机与内机噪音值', '常年开的家庭电费差距明显，看能效等级', '', ''],
                        ],
                        [
                            'key' => 'budget', 'text' => '这块预算大概？', 'type' => 'single',
                            'options' => ['3 万以内', '3~4.5 万', '4.5 万以上', '还没概念'],
                        ],
                    ],
                ]],
            ],
        ]);
        foreach (range(1, 28) as $i) {
            Stance::factory()->for($survey, 'matter')->create([
                'mode' => Stance::MODE_REGISTER,
                'payload' => ['answers' => [
                    'rooms' => fake()->randomElement(['3 个（1 拖 3）', '4 个（1 拖 4）', '4 个（1 拖 4）', '5 个（1 拖 5）']),
                    'priority' => fake()->randomElement(['静音', '静音', '省电', '价格']),
                    'budget' => fake()->randomElement(['3 万以内', '3~4.5 万', '3~4.5 万', '4.5 万以上', '还没概念']),
                ]],
            ]);
        }

        // 问答区：已答的沉淀在前，热门未答的等团长处理
        $answered = MatterQuestion::factory()->for($hvac)->answered(
            '毛坯层高 2.95 米，中央空调局部吊顶后过道约 2.6 米，客厅主体不吊不受影响。量房时会逐户确认。',
            '3栋 · 老K',
        )->create(['content' => '吊顶之后层高还剩多少？会不会压抑？']);
        $answered->echoers()->attach(Resident::factory()->count(5)->create());

        $pending = MatterQuestion::factory()->for($hvac)->create(['content' => '内机保修几年？坏了上门费谁出？']);
        $pending->echoers()->attach(Resident::factory()->count(12)->create());
    }

    /** 装修公司 · 标品团（接龙中）：条款已谈定，确认参团为主、少量意向没回来确认。 */
    private function decorationGroupbuy(): void
    {
        $decoration = Matter::factory()->open()->for($this->leader, 'initiator')->create([
            'category' => '装修公司',
            'title' => '「城建装饰」整装团购',
            'target_count' => 20,
            'payload' => [
                'pitch' => '我自己家就签的这家，合同、报价单、工地进度全部公开，大家随时来我家工地看。谈下来的条件对每家一样。',
                'perk' => '满 20 人赠全屋水电升级（点位不限量）',
                'terms' => [
                    ['label' => '半包', 'value' => '618 元/㎡（门市 688）'],
                    ['label' => '全包', 'value' => '1150 元/㎡（门市 1280）'],
                    ['label' => '质保', 'value' => '隐蔽工程 10 年，写入合同'],
                ],
                'glossary' => [
                    [
                        'term' => '半包 vs 全包',
                        'explain' => '半包=人工+辅料，主材自己买；全包=全含，差价大头在主材品牌。',
                        'judge' => '有时间盯主材、想控品牌选半包；工作忙、想省心选全包，但一定先看主材清单再比价。',
                        'caution' => '全包报价先问主材品牌和型号档次，「同品牌」不等于同系列。',
                    ],
                    [
                        'term' => '水电点位',
                        'explain' => '每个插座、开关、出水口算一个点位，按个收费。',
                        'judge' => '三房通常 80~120 个点位，先数自家电器再对报价。',
                        'caution' => '按点位计费最容易超预算，「点位不限量」才是实在让利。',
                    ],
                    [
                        'term' => '隐蔽工程质保',
                        'explain' => '封进墙里的水电和防水，出问题维修最贵。',
                        'judge' => '质保 10 年起步才有意义，低于这个年限要压价。',
                        'caution' => '口头承诺无效，年限必须写进合同条款。',
                    ],
                ],
            ],
        ]);
        // 接龙中：确认参团 17 户（计入成团），另有 3 个意向还没回来确认
        Stance::factory()->count(17)->for($decoration, 'matter')->create(['payload' => ['share_contact' => true, 'stage' => Stance::JOIN_STAGE_CONFIRMED]]);
        Stance::factory()->count(3)->intent()->for($decoration, 'matter')->create();
        MatterUpdate::factory()->for($decoration, 'matter')->create([
            'happened_on' => now()->subDays(7)->toDateString(),
            'content' => '团长家水电开槽完成，横平竖直验收通过',
        ]);
        MatterUpdate::factory()->for($decoration, 'matter')->create([
            'happened_on' => now()->subDays(19)->toDateString(),
            'content' => '团长家开工交底，报价单（脱敏）已公开',
        ]);

        MatterQuestion::factory()->for($decoration)->answered(
            '可以。半包转全包按主材清单补差价，开工前都能改，写在合同补充条款里。',
            '3栋 · 老K',
        )->create(['content' => '先报半包，后面想改全包可以吗？']);
    }

    /** 门窗 · 商家直供团（意向征集）：已认证商家发起、带商家署名，商家成员在问答区以店名回答。 */
    private function windowGroupbuy(Party $window, Resident $staff): void
    {
        $windows = Matter::factory()->create([
            'initiator_id' => $staff->id,
            'initiator_party_id' => $window->id,
            'category' => '门窗',
            'title' => '断桥铝封窗团购（商家直供）',
            'target_count' => 15,
            'payload' => [
                'pitch' => '工厂直营，按团购价封阳台和飘窗，先征集意向，满 15 户约时间统一上门测量。',
                'perk' => '满 15 户送金刚网纱窗',
                'terms' => [['label' => '意向价', 'value' => '断桥铝 60 系 899 元/㎡ 起']],
                'glossary' => [
                    [
                        'term' => '断桥铝',
                        'explain' => '铝合金中间隔一层隔热条，冬天不结露、隔音更好。',
                        'judge' => '临街或西晒的户型值得上，安静朝内的低楼层普通铝也够用。',
                        'caution' => '型材壁厚要 1.4mm 以上，低价团常在壁厚上缩水，让商家写进合同。',
                    ],
                ],
            ],
        ]);
        Stance::factory()->count(7)->intent()->for($windows, 'matter')->create();

        MatterQuestion::factory()->for($windows)->answered(
            '60 系指型材宽度 60mm，隔音隔热主要看玻璃配置，团购价含 5+12A+5 双层中空玻璃，可加价升三玻两腔。',
            '优居门窗',
        )->create(['content' => '60 系和 70 系差在哪？团购价是什么玻璃？']);
    }

    /** 地暖 · 已成团：成交公示 + 参团邻居的评价，给后来的团打样。 */
    private function floorHeatingDone(): void
    {
        $heating = Matter::factory()->done()->for($this->leader, 'initiator')->create([
            'category' => '地暖',
            'title' => '水地暖团购（已成团）',
            'target_count' => 20,
            'payload' => [
                'pitch' => '供暖季前装完，21 户一起谈的价格。',
                'perk' => '',
                'terms' => [
                    ['label' => '全屋水地暖', 'value' => '198 元/㎡（含分集水器）'],
                    ['label' => '锅炉', 'value' => '威能 26kW 两用炉，团购价 9800'],
                ],
                'glossary' => [],
                'final_terms' => [
                    ['label' => '成交价', 'value' => '全屋 198 元/㎡ + 锅炉 9800'],
                    ['label' => '成交户数', 'value' => '21 户'],
                    ['label' => '质保', 'value' => '管路 50 年质保书面交付'],
                ],
                'final_note' => '返点已按人头折成每户 300 元尾款减免，明细在团长处可查。',
            ],
        ]);
        Stance::factory()->count(21)->for($heating, 'matter')->create(['payload' => ['share_contact' => true, 'stage' => Stance::JOIN_STAGE_CONFIRMED]]);
        Stance::factory()->review(5, '施工很规范，打压测试当着业主做的，价格比门市谈省了小一万')->for($heating, 'matter')->create();
        Stance::factory()->review(5, '团长全程盯得紧，锅炉是真便宜')->for($heating, 'matter')->create();
        Stance::factory()->review(3, '装完不错，就是排期等了两周，人多的团要有心理准备')->for($heating, 'matter')->create();
        MatterUpdate::factory()->for($heating, 'matter')->create([
            'happened_on' => now()->subDays(5)->toDateString(),
            'content' => '全部 21 户完工，成交公示已挂出，评价通道开放',
        ]);
    }

    /** 瓷砖 · 未成团：人数不够体面收场，名单封存、不开评价——收场也是给邻居的交代。 */
    private function tileAborted(): void
    {
        $tiles = Matter::factory()->aborted()->for($this->leader, 'initiator')->create([
            'category' => '瓷砖',
            'title' => '柔光砖团购（未成团）',
            'target_count' => 20,
            'payload' => [
                'pitch' => '柔光砖看着高级不刺眼，想凑 20 户找厂家谈。',
                'perk' => '',
                'terms' => [],
                'glossary' => [[
                    'term' => '柔光砖',
                    'explain' => '介于亮面和哑光之间的釉面，反光柔和。「金丝绒釉面」这类名字本质都是柔光砖。',
                    'judge' => '客厅采光一般的选柔光不压暗，采光很好的亮面柔光都行。',
                    'caution' => '别为营销名字加钱，同一工艺换个名字贵三成的很常见。',
                ]],
            ],
        ]);
        Stance::factory()->count(5)->intent()->for($tiles, 'matter')->create();
        MatterUpdate::factory()->for($tiles, 'matter')->create([
            'happened_on' => now()->subDays(1)->toDateString(),
            'content' => '两周只凑到 5 户，这次先收场；等交房集中期人多了再开二期',
        ]);
    }

    /** 全屋定制 · 意向征集：刚起盘的团。 */
    private function wardrobeSeeking(): void
    {
        Matter::factory()->for($this->leader, 'initiator')->create([
            'category' => '全屋定制',
            'title' => '全屋定制柜体 意向征集',
            'target_count' => 15,
            'payload' => [
                'pitch' => '衣柜、餐边柜、玄关柜一起谈按投影面积的一口价。先看有多少家感兴趣。',
                'perk' => '',
                'terms' => [],
                'glossary' => [[
                    'term' => '颗粒板 vs 多层实木',
                    'explain' => '颗粒板便宜、环保看等级，是主流选择；多层实木贵约三成、更防潮。',
                    'judge' => '干区柜体颗粒板足够；厨卫旁、飘窗下这类潮气重的位置再考虑多层实木。',
                    'caution' => '环保等级认准 ENF 级板材，「进口板」三个字不等于环保，要看检测报告。',
                ]],
            ],
        ]);
    }

    /** 公告：小区消息的权威沉淀。 */
    private function notices(): void
    {
        Matter::factory()->notice()->create([
            'title' => '本小程序公益运营说明',
            'payload' => [
                'body' => '本小程序由小区业主公益运营：不代收任何款项，签约付款由业主直接对商家；商家给到的任何返点，全部转为参团业主让利，并在成团后随成交公示摊开。',
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
    }

    /** 维权：对开发商/物业的集体发声（名单不对外公示）。 */
    private function rightsActions(): void
    {
        $rights = Matter::factory()->rights()->for($this->leader, 'initiator')->create([
            'title' => '地下车位定价过高，联名要求公开成本',
            'target_count' => 100,
            'payload' => [
                'pitch' => '销售口径车位 15.8 万一个，周边同类小区普遍 10~12 万。凑满 100 人联名，正式向开发商递交问询函，要求公开车位定价依据并给出团购价。',
            ],
        ]);
        Stance::factory()->count(47)->for($rights, 'matter')->create();

        $inspection = Matter::factory()->rights()->for($this->leader, 'initiator')->create([
            'state' => 'negotiating',
            'title' => '精装交付标准与样板间不符，集体交涉中',
            'target_count' => 50,
            'payload' => [
                'pitch' => '有邻居从工地照片发现门槛石、卫浴五金与样板间标注品牌不一致。已凑齐 50 人联名并递交开发商，等待书面答复，进展会更新在本页。',
            ],
        ]);
        Stance::factory()->count(52)->for($inspection, 'matter')->create();
        MatterUpdate::factory()->for($inspection, 'matter')->create([
            'happened_on' => now()->subDays(3)->toDateString(),
            'content' => '开发商客服已签收联名函，承诺 15 个工作日内书面答复',
        ]);
    }

    /** 活动 + 互助：邻里协作，活动开放联系互通（拉群约时间）。 */
    private function activityAndAid(): void
    {
        $activity = Matter::factory()->activity()->for($this->leader, 'initiator')->create([
            'title' => '周六建材市场组团踩点（第二期）',
            'target_count' => 15,
            'payload' => [
                'pitch' => '这周六上午去富森美建材市场，主看门窗和全屋定制，有经验的邻居带队讲怎么看材料。上期去了 8 家人，收获很大。地铁站集合，报名后拉小群。',
            ],
        ]);
        Stance::factory()->count(9)->for($activity, 'matter')->create(['payload' => ['share_contact' => true]]);

        $aid = Matter::factory()->aid()->for($this->leader, 'initiator')->create([
            'title' => '拼车去工地看进度（本周日上午）',
            'payload' => [
                'pitch' => '本周日上午去项目工地看施工进度，我开车有 3 个空位，住得近的邻居可以拼车，油费不用给，人齐出发。',
            ],
        ]);
        Stance::factory()->count(3)->for($aid, 'matter')->create(['payload' => ['share_contact' => true]]);
    }

    /** 装修意向摸底：全小区的基础盘（数据 tab 的主内容），选项解释示范「问卷即教育」。 */
    private function renovationCensus(): void
    {
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
                'modules' => [
                    [
                        'key' => 'basic',
                        'title' => '基础登记',
                        'intro' => '约 2 分钟 · 明细仅管理员可见，对外只展示汇总统计',
                        'questions' => [
                            ['key' => 'layout', 'text' => '你家是哪个户型？', 'type' => 'single', 'options' => ['107㎡', '130㎡', '154㎡'], 'required' => true],
                            [
                                'key' => 'decoration_mode', 'text' => '打算怎么装？', 'type' => 'single', 'required' => true,
                                'options' => ['全包（都交给装修公司）', '半包（主材自己买）', '清包（只请工人）', '还没定'],
                                'option_notes' => ['材料人工全含，省心但要盯主材清单', '人工辅料给装修公司，主材品牌自己控', '最省钱也最费精力，适合懂行的', '选这个也是有效信息'],
                            ],
                            ['key' => 'interests', 'text' => '对哪些团购感兴趣？', 'type' => 'multi', 'options' => ['装修公司', '中央空调', '地暖', '全屋定制', '门窗', '软装家具', '瓷砖'], 'required' => true],
                        ],
                    ],
                    ...$this->surveyModules(),
                ],
            ],
        ]);
        Stance::factory()->count(46)->censusAnswers()->create(['matter_id' => $census->id]);
    }

    /** 物业署名调研：治理方的正当用例——亮明发起方、结果对全小区公开。 */
    private function propertyCensus(Party $property): void
    {
        $charging = Matter::factory()->create([
            'type' => 'census',
            'initiator_id' => null,
            'initiator_party_id' => $property->id,
            'state' => 'open',
            'category' => '车库',
            'title' => '地下车库充电桩加装需求调研',
            'target_count' => 0,
            'payload' => [
                'pitch' => '物业计划分批给地下车库加装充电桩，先摸清真实需求再定点位和数量，结果对全小区公开。',
                'modules' => [[
                    'key' => 'ev',
                    'title' => '充电需求',
                    'questions' => [
                        ['key' => 'has_ev', 'text' => '家里有新能源车吗？', 'type' => 'single', 'options' => ['已有', '一年内会买', '暂无计划'], 'required' => true],
                        [
                            'key' => 'charge_mode', 'text' => '倾向哪种充电方式？', 'type' => 'single',
                            'options' => ['私人桩（自有车位安装）', '公共快充（按度付费）', '都行'],
                            'option_notes' => ['需要自有产权车位，物业协助报装', '不占车位，适合没买车位的住户', ''],
                        ],
                    ],
                ]],
            ],
        ]);
        foreach (range(1, 31) as $i) {
            Stance::factory()->for($charging, 'matter')->create([
                'mode' => Stance::MODE_REGISTER,
                'payload' => ['answers' => [
                    'has_ev' => fake()->randomElement(['已有', '已有', '一年内会买', '暂无计划', '暂无计划']),
                    'charge_mode' => fake()->randomElement(['私人桩（自有车位安装）', '公共快充（按度付费）', '都行']),
                ]],
            ]);
        }
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
