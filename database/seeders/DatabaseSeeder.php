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
        $this->leader = Resident::factory()->inUnit('3栋')->create(['nickname' => '老K', 'is_admin' => true, 'layout_label' => '130㎡']);

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
        $this->centralAcPrimerCensus();
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

    /**
     * 中央空调科普征集：纯知识分享、助人为乐，不是商家、也不为组织团购。
     * 结合本小区实际——每户仅 1 个外机位、层高统一 2.85 米，所以只能走一拖多、风管机排除、层高不必再问——
     * 每题都带讲解、每个选项都带一句「为什么」，答一遍就能建立基本认知：
     * 从「氟机还是水机」「两联供 / 三联供」到「一拖几与外机匹数、地暖热水新风、品牌预算、怎么给自己避坑」，顺着答完就是一条完整的入门路径。
     * 行情数字取自 2025—2026 年中国市场公开报价，仅供建立量级概念。
     */
    private function centralAcPrimerCensus(): void
    {
        $modules = $this->centralAcModules();

        $census = Matter::factory()->create([
            'type' => 'census',
            'initiator_id' => null,
            'state' => 'open',
            'category' => '中央空调',
            'title' => '中央空调怎么选 · 答题即入门',
            'target_count' => 0,
            'payload' => [
                'pitch' => '第一次装修、完全没接触过中央空调？这份问卷把该懂的都串成了题：每题有讲解、选项下写清「为什么」，答一遍 5 分钟就能建立基本认知。纯科普、不推销。',
                'modules' => $modules,
            ],
        ]);

        // 用同一份 schema 生成示例答案，选项标签绝不跑偏；必答题人人答、其余按真实场景随机跳过一部分
        $questions = collect($modules)->flatMap(fn (array $module): array => $module['questions'])->keyBy('key');
        foreach (range(1, 38) as $i) {
            $answers = [];
            foreach ($questions as $key => $question) {
                if (! ($question['required'] ?? false) && ! fake()->boolean(75)) {
                    continue;
                }
                $options = $question['options'];
                $answers[$key] = $question['type'] === 'multi'
                    ? fake()->randomElements($options, fake()->numberBetween(1, min(3, count($options))))
                    : fake()->randomElement($options);
            }
            Stance::factory()->for($census, 'matter')->create([
                'mode' => Stance::MODE_REGISTER,
                'payload' => ['answers' => $answers],
            ]);
        }
    }

    /**
     * 中央空调科普问卷题库：讲解写在 note（题干下）和 option_notes（选项下），答题即建概念。
     *
     * @return array<int, array<string, mixed>>
     */
    private function centralAcModules(): array
    {
        return [
            [
                'key' => 'basics',
                'title' => '先认识它',
                'intro' => '中央空调 = 一台外机装在室外的空调机位，通过吊顶里的管道带动客厅和各房间的内机，出风口嵌在吊顶里，比挂机、柜机更整洁。咱们小区每户就一个机位，装的都是「一拖多」——一台外机带好几个内机。先认个门，再定个大方向：氟机还是水机。',
                'questions' => [
                    [
                        'key' => 'know_level', 'text' => '你现在对中央空调了解到哪一步了？', 'type' => 'single', 'required' => true,
                        'note' => '不用有压力，这份问卷每题都带讲解，答一遍就能入门。',
                        'options' => ['完全没概念，第一次了解', '听说过，但不知道怎么选', '做过一些功课', '已经比较清楚了'],
                        'option_notes' => ['正好，跟着往下答就行', '这份问卷就是帮你把「怎么选」串起来', '看看有没有补充的盲区', '当复习，也给拿不准的邻居留个参考'],
                    ],
                    [
                        'key' => 'system_pref', 'text' => '氟机还是水机，你更倾向哪种？', 'type' => 'single',
                        'note' => '这俩差在室内「用什么传冷热」。氟机（多联机）室内走冷媒：制冷快、系统成熟、性价比高，是绝大多数家庭的选择，缺点是出风偏凉偏干。水机室内走水：出风柔和、不干燥、体感更舒服，适合追求舒适或大面积，缺点是造价高、制冷稍慢。（要不要带地暖、热水是另一回事，后面「联供」会讲——氟机、水机都能配地暖。）',
                        'options' => ['氟机 / 多联机（主流、性价比高）', '水机（出风更柔和、造价高）', '还分不清，想让人现场讲讲'],
                        'option_notes' => [
                            '制冷快、成熟、性价比高，九成家庭的选择',
                            '出风柔和不干燥、更舒适，预算充足、在意体感可以考虑',
                            '分不清很正常，让懂行的邻居或商家按你家户型讲一遍最直观',
                        ],
                    ],
                ],
            ],
            [
                'key' => 'sizing',
                'title' => '一拖几、外机多少匹',
                'intro' => '报价单看着吓人，配置核心就两件事：装几个内机（一拖几），外机要多大（多少匹）。这一节讲明白。',
                'questions' => [
                    [
                        'key' => 'internal_units', 'text' => '打算装几个内机？（客厅和每个卧室各算一个）', 'type' => 'single', 'required' => true,
                        'note' => '「一拖几」的「几」= 内机总数。客厅（含餐厅）通常算 1 个，每间卧室各 1 个——别漏了客厅。咱们小区大致：107㎡ 多是一拖三、130㎡ 一拖四、154㎡ 一拖五。另外，客厅要是长条形，内机得选「高静压」的，风才送得到最里头。',
                        'options' => ['3 个（客厅 + 2 间房，一拖三）', '4 个（客厅 + 3 间房，一拖四）', '5 个（客厅 + 4 间房 / 含书房，一拖五）', '还没数过'],
                        'option_notes' => ['107㎡ 常见配置', '130㎡ 最典型', '154㎡ 或房间多的', '照着「客厅 + 卧室数」数一下就有了'],
                    ],
                    [
                        'key' => 'same_time', 'text' => '家里通常几个房间会同时开空调？', 'type' => 'single',
                        'note' => '内机总功率可以超过外机（行话「超配」，一般到 125%~130%）。外机型号里的数字就是制冷量：140 约 5 匹、160 约 6 匹、180 约 7 匹。一拖四常配 140~160、一拖五常配 160~180；同开的房间越多，外机数字要越大，否则「带不动」、越用越不凉。',
                        'options' => ['各屋独立，很少同时开', '客厅 + 主卧常一起开', '全家几乎同时开'],
                        'option_notes' => ['可以适当超配，外机选小一档省钱', '按主要生活动线配，最常见', '外机匹数要给足，别被最低价的小一号外机忽悠'],
                    ],
                ],
            ],
            [
                'key' => 'combo',
                'title' => '地暖 · 热水 · 新风',
                'intro' => '中央空调主要管夏天制冷。冬天要不要地暖、要不要顺带解决洗澡热水和通风换气——这些管路都得在装修前预留，后补是拆吊顶、刨地面的大工程，最好一起定。',
                'questions' => [
                    [
                        'key' => 'combo_needs', 'text' => '除了夏天制冷，你还想让这套系统顺带解决什么？', 'type' => 'single',
                        'note' => '一套主机能兼顾几件事，行话叫「联供」：只装中央空调 = 夏天制冷、冬天吹热风；两联供 = 中央空调 + 地暖，冬天用地暖采暖、暖脚不干；三联供 = 再加一路生活热水，洗澡也一起解决。带地暖有两种做法：天氟地水（空调走氟、地暖走水，便宜些）和天水地水（都走水，更柔和舒适、更贵）。',
                        'options' => ['只要中央空调（制冷 + 制热）', '空调 + 地暖（两联供）', '空调 + 地暖 + 热水（三联供）', '还没想好'],
                        'option_notes' => [
                            '预算最省；冬天靠空调吹热风，怕冷怕干可留意带「喷气增焓」的机型',
                            '加地暖更舒服、暖脚不干；分天氟地水（省）和天水地水（更柔和、更贵）两种做法',
                            '空调、地暖、洗澡热水一套全包，想一步到位的可以了解',
                            '答完再定也不迟，先让施工把地暖、热水的管路预留出来',
                        ],
                    ],
                    [
                        'key' => 'fresh_air', 'text' => '要不要预留新风系统？', 'type' => 'single',
                        'note' => '新风 = 长期关窗也能把室外空气过滤后送进来、把脏空气排出去，和空调是两套独立管路。要装最好和空调一起在吊顶阶段预留，后补要重新吊顶。',
                        'options' => ['要，在意空气质量', '看情况，先留个位置', '不需要'],
                        'option_notes' => ['雾霾天、临街、有小孩老人的家庭值得', '至少让施工把管路和点位预留出来，以后想装不折腾', '简单省事，靠开窗通风'],
                    ],
                ],
            ],
            [
                'key' => 'machine_cost',
                'title' => '静音、省电、品牌与预算',
                'intro' => '这一节把「机器素质」和「花多少钱」一起过一遍。品牌大致分进口、国产两档，差价约三四成。',
                'questions' => [
                    [
                        'key' => 'quiet_pref', 'text' => '对卧室静音、冬天制热在不在意？', 'type' => 'single',
                        'note' => '这两点主要看压缩机（空调的「心脏」）和内机噪音。压缩机常见「转子式」和更高端的「涡旋式」（更静、更耐用、也更贵）；同是转子，双转子比单转子更平稳安静；带「喷气增焓」的低温制热更给力，冬天冷也不掉链子。好内机最低档能到 20 分贝出头，让商家写明压缩机型号和噪音值。',
                        'options' => ['很在意（卧室要安静、冬天要够暖）', '一般', '不太在意'],
                        'option_notes' => ['关注双转子 + 喷气增焓的机型，别只看总价', '主流机型即可', '按性价比选就行'],
                    ],
                    [
                        'key' => 'energy_care', 'text' => '你有多在意以后的电费？', 'type' => 'single',
                        'note' => '认准「一级能效 + 全直流变频」——能效看 APF（全年能效比）数值，越高越省电；变频比定频省 20%~30%。常年开的家庭，能效差一级，一年电费能差好几百到上千。',
                        'options' => ['很在意，基本常年开', '一般，够用就行', '只有夏天开一阵'],
                        'option_notes' => ['优先一级能效，长期省的电费能把差价赚回来', '主流一级能效机型即可，不必追顶配', '用得少的话，能效可以不用卡到最顶'],
                    ],
                    [
                        'key' => 'brand_lean', 'text' => '你更倾向哪一档品牌？', 'type' => 'single',
                        'note' => '进口一线以大金、日立（海信日立）为代表；国产一线是美的、格力、海尔、海信。技术都成熟，主要差在品牌溢价和售后网点。',
                        'options' => ['进口一线（大金 / 日立，贵约 30%~40%）', '国产一线（美的 / 格力 / 海尔 / 海信，性价比高）', '不认牌子，只看方案和售后', '还不了解，先看看'],
                        'option_notes' => [
                            '产品成熟、口碑稳，预算充足、追求省心可以上',
                            '同样一级能效变频，价格更友好，是近年主流选择',
                            '成熟的想法：内机配置、安装工艺和售后往往比牌子更影响体验',
                            '没关系，先把需求摸清，牌子最后再定',
                        ],
                    ],
                    [
                        'key' => 'budget', 'text' => '这块预算大概多少？', 'type' => 'single',
                        'note' => '给个量级参考（2025—2026 行情）：一拖四 / 五的氟机，国产约 2.5 万~3.5 万、进口约 3.5 万~4.5 万起；水机、两联供 / 三联供更贵。报价常不含超长铜管、检修口等辅材，问价时一定要问「这是不是全包价」。',
                        'options' => ['3 万以内', '3 万~4 万', '4 万~5 万', '5 万以上', '还没概念'],
                        'option_notes' => ['国产入门一拖三 / 四', '国产一拖四 / 五的常见区间', '进口，或大户型 / 水机', '高端水机 / 两联供、三联供 / 大平层', '看完区间再回来估一个'],
                    ],
                ],
            ],
            [
                'key' => 'pitfalls',
                'title' => '最后，给自己一份避坑清单',
                'intro' => '中央空调「三分产品、七分安装」——同样的机器，装得好不好，制冷、噪音、漏不漏水差很多，而且大头都藏在看不见的地方。收藏这几条，签合同、验收时自己照着一项项对，就不容易被坑。',
                'questions' => [
                    [
                        'key' => 'watch_points', 'text' => '这些坑，哪些你想重点记一记？（可多选）', 'type' => 'multi',
                        'note' => '这几条几乎覆盖了中央空调最常见的纠纷，看懂它们，你就从「小白」变成「问得出行话」的业主了。',
                        'options' => [
                            '报价是不是「全包价」（含辅材、安装、检修口、冷凝水管）',
                            '内外机型号、匹数和合同一致（防偷换小一号外机）',
                            '铜管品牌、壁厚有没有写进合同',
                            '是不是正品行货（串货机不保修）',
                            '整机保修几年（常见 6 年）+ 固定安装班组',
                            '安装做没做「打压保压 + 抽真空」（关键验收）',
                        ],
                        'option_notes' => [
                            '「机器价」低不代表总价低，辅材和安装才是隐藏大头',
                            '外机被换成小一号最难发现，也最影响制冷效果',
                            '口头承诺无效，规格、品牌都要落到合同上；加长管往往另收费（约每米上百元）',
                            '串货机 / 无码机厂家不保修，要求提供可查询的正规机器码',
                            '施工外包、班组不固定最容易出工艺问题和扯皮',
                            '铜管要保压测漏、抽真空排杂气，省这步以后容易漏氟、不制冷；检修口也要留够、好打开',
                        ],
                    ],
                ],
            ],
        ];
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
