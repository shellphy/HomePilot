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
        $this->hardFinishCensus();
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
     * 硬装科普征集（装修题库之一，与软装 / 全屋定制拆开）：纯知识分享、不推销。
     * 面向第一次装修、对硬装工艺完全没概念的业主，参考《房屋装修全流程指南》，
     * 每题带讲解、每个选项带一句「为什么」，答一遍就能建立基本认知：
     * 从「硬装是什么」到拆改水电、防水瓷砖、吊顶墙面门、环保预算、验收避坑，顺着答完就是一条入门路径。
     */
    private function hardFinishCensus(): void
    {
        $modules = $this->hardFinishModules();

        $census = Matter::factory()->create([
            'type' => 'census',
            'initiator_id' => null,
            'state' => 'open',
            'category' => '装修',
            'title' => '硬装怎么做 · 答题即入门',
            'target_count' => 0,
            'payload' => [
                'purpose' => '第一次装修、完全搞不懂硬装工艺？这份问卷把该懂的都串成了题：每题有讲解、选项下写清「为什么」，答一遍就能建立基本认知。纯科普、不推销。',
                'modules' => $modules,
            ],
        ]);

        $questions = collect($modules)->flatMap(fn (array $module): array => $module['questions'])->keyBy('key');
        foreach (range(1, 42) as $i) {
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
     * 硬装科普问卷题库：讲解写在 note（题干下）和 option_notes（选项下），答题即建概念。
     * 知识点取自《房屋装修全流程指南》，为小白建立认知，非施工手册。
     *
     * @return array<int, array<string, mixed>>
     */
    private function hardFinishModules(): array
    {
        return [
            [
                'key' => 'basics',
                'title' => '先认识硬装',
                'intro' => '装修分硬装和软装。硬装 = 拆改、水电、防水、瓷砖、吊顶、墙面、门这些「埋进去、装完难改」的基础工程（隐蔽工程），是房子的骨架；软装 = 家具、窗帘、灯具这些能搬走的。硬装做错返工代价最大，先把该懂的过一遍。',
                'questions' => [
                    [
                        'key' => 'know_level', 'text' => '你对硬装了解到哪一步了？', 'type' => 'single', 'required' => true,
                        'note' => '不用有压力，这份问卷每题都带讲解，答一遍就能入门。',
                        'options' => ['完全没概念，第一次了解', '听过一些名词，但不懂工艺', '做过一些功课', '已经比较清楚了'],
                        'option_notes' => ['正好，跟着往下答就行', '这份问卷帮你把名词背后的门道串起来', '看看有没有补充的盲区', '当复习，也给拿不准的邻居留个参考'],
                    ],
                    [
                        'key' => 'worries', 'text' => '硬装这块，你最担心 / 最想搞懂什么？（可多选）', 'type' => 'multi',
                        'note' => '先摸清你的关注点，这份问卷后面会一一覆盖。',
                        'options' => ['工艺被偷工减料', '环保、甲醛', '超预算', '不懂行被坑', '后期维修要砸墙', '效果不好看'],
                        'option_notes' => [
                            '硬装大量藏在墙里，验收看不见，最容易缩水',
                            '不只甲醛——苯（致白血病）、氡（石材放射性）、TVOC 危害同样大，都靠认证把关',
                            '主材和隐蔽工程是大头，先分清钱该花哪',
                            '看懂几个关键工艺，就从「小白」变成「问得出行话」',
                            '隐蔽工程装完难改，所以每步都要盯、要验收',
                            '风格效果七分靠硬装打底、三分靠软装点缀',
                        ],
                    ],
                ],
            ],
            [
                'key' => 'plumbing',
                'title' => '拆改与水电（隐蔽工程）',
                'intro' => '正式开工前，记得先到物业备案、交装修押金、办开工证，这步别漏。水电是埋进墙地里的「隐蔽工程」，装完最难改、也最影响日后生活，是硬装里最要上心的一步。',
                'questions' => [
                    [
                        'key' => 'demolition', 'text' => '你家要拆改墙体、改户型吗？', 'type' => 'single',
                        'note' => '承重墙（结构图上的实心墙）绝对不能拆；新建墙和原墙的交接处要「挂网」，否则后期几乎必开裂、还很难修。',
                        'options' => ['大改（拆墙 + 新建，重塑格局）', '小改（拆个非承重墙 / 垭口）', '基本不动', '还没想好'],
                        'option_notes' => ['先让设计师对着结构图确认哪些能拆', '非承重墙可动，交接处记得挂网', '省心省钱，原格局够用就不折腾', '量房时对着图纸一起定'],
                    ],
                    [
                        'key' => 'points', 'text' => '插座、开关点位你心里有数吗？', 'type' => 'single',
                        'note' => '点位 = 每个插座和开关，埋进墙里、装完就加不了，原则是「宁多勿少」。电线规格：照明 2.5 平方、插座 4 平方、空调和厨卫大功率不小于 6 平方；强电弱电要分开走、别同槽。',
                        'options' => ['已经按家电清单列过', '大概知道要多留几个', '完全没头绪'],
                        'option_notes' => ['最靠谱：先定家电型号再排点位', '重点多留：厨房台面、床头、沙发、餐桌下', '照「每个电器 + 备用」的思路数一遍就有了'],
                    ],
                    [
                        'key' => 'water_extras', 'text' => '这些系统要不要提前预留？（可多选，都得在水电阶段定）', 'type' => 'multi',
                        'note' => '净水软水、零冷水、中央吸尘这类要提前布管、留电位，后补基本做不了。',
                        'options' => ['前置 / 中央净水', '软水机', '直饮水（RO 净水机）', '零冷水热水', '中央吸尘系统', '暂不考虑'],
                        'option_notes' => [
                            '前置滤大颗粒杂质、中央净水改善全屋水质',
                            '软化水质，洗澡不涩、少水垢，护热水器和皮肤',
                            '厨房末端装 RO，出直饮水，可接管线机 / 冰箱',
                            '打开龙头就出热水、不用放冷水，需带零冷水功能的热水器',
                            '插座式吸口预埋墙里，打扫不用拖着主机',
                            '简单省事，后面想上再看',
                        ],
                    ],
                    [
                        'key' => 'smart_wiring', 'text' => '网络、智能这些弱电要不要提前布？（可多选）', 'type' => 'multi',
                        'note' => '弱电（网络、智能）也在水电阶段布线，后补要砸墙。全屋网线走「六类或以上」拉到每个房间 + AP 面板，比无线中继稳；很多智能开关要「零火线」，得让水电在开关底盒预留零线；智能锁、可视对讲、摄像头也要提前留线和电源。',
                        'options' => ['全屋有线网络 + AP 面板', '智能灯光开关（需预留零线）', '智能门锁 / 可视对讲', '摄像头 / 安防', '暂不做，靠无线补'],
                        'option_notes' => [
                            '网线到各房间，WiFi 更稳，别只靠路由器打天下',
                            '想玩智能开关，务必让水电在底盒留零线，否则受限',
                            '门口这套要提前留线和电源',
                            '点位和供电提前规划，后期加装很麻烦',
                            '够用也行，但布线是「现在不做以后难补」的事',
                        ],
                    ],
                ],
            ],
            [
                'key' => 'heating_window',
                'title' => '供暖 · 门窗',
                'intro' => '地暖要在贴砖前铺、换窗属于拆改，这些都得趁早定，插在硬装前段。',
                'questions' => [
                    [
                        'key' => 'heating', 'text' => '要装地暖吗？（地暖在瓦工贴砖前铺）', 'type' => 'single',
                        'note' => '南方没集中供暖，取暖靠自己装。水地暖在贴砖前铺：挤塑板保温层 + 反射膜 + 地暖管 + 豆石回填，会占 5~8cm 层高、也影响地面找平；分水器要纯铜、旁边留插座位（后期不热能加循环泵）。电地暖适合卫生间等小面积。暖气片（挂暖）不占层高、升温快，但占墙面。',
                        'options' => ['水地暖（全屋）', '电地暖（局部 / 小面积）', '暖气片（不占层高）', '不装 / 已有', '还没定'],
                        'option_notes' => [
                            '最舒服、最主流；记得挤塑板保温、纯铜分水器、系统打压',
                            '升温快、适合小面积，大面积长期开电费高',
                            '不占层高、升温快，缺点是占墙面、墙面易熏黑',
                            '用空调制热或已有供暖就先不折腾',
                            '取暖方式趁早定，别等贴完砖',
                        ],
                    ],
                    [
                        'key' => 'windows', 'text' => '窗户、阳台有什么打算？（可多选）', 'type' => 'multi',
                        'note' => '换窗属于拆改阶段的活。开发商原窗隔音隔热差的，可换「断桥铝」（型材带隔热条 + 中空玻璃，隔音隔热好）；封阳台能扩使用空间，但要做好防水和承重；阳台做洗衣区要提前留水电和地漏。',
                        'options' => ['换断桥铝窗（隔音隔热）', '封阳台（扩空间）', '阳台做洗衣 / 家务区', '原窗够用、不动', '还没定'],
                        'option_notes' => [
                            '临街、西晒、隔音差的值得换；认准型材壁厚和玻璃配置',
                            '扩出使用面积，注意防水和荷载，别拆到承重',
                            '要提前留上下水、防水和插座',
                            '省一笔；原窗不漏、不隔音差就够用',
                            '换窗趁早，它影响后面所有工序',
                        ],
                    ],
                ],
            ],
            [
                'key' => 'tiling',
                'title' => '防水 · 瓷砖 · 地面 · 美缝',
                'intro' => '防水和瓷砖是瓦工阶段的活：防水没做好后期渗漏要砸砖返工，瓷砖则直接定家里的调子。',
                'questions' => [
                    [
                        'key' => 'waterproof', 'text' => '关于防水，你了解多少？', 'type' => 'single',
                        'note' => '卫生间墙地面要全做防水、淋浴区墙面上返到顶；厨房、阳台有地漏才做。防水做完必须做 48 小时「闭水试验」（放水泡一天两夜看漏不漏），合格才能贴砖——这步千万别省。',
                        'options' => ['知道要做闭水试验', '只知道要做防水', '完全不懂'],
                        'option_notes' => ['很好，验收时盯紧这一步', '记住关键动作是「48 小时闭水试验」', '记住一句：卫生间全做防水 + 闭水试验'],
                    ],
                    [
                        'key' => 'tile_finish', 'text' => '瓷砖表面你偏好哪种？', 'type' => 'single',
                        'note' => '按光泽度分：亮光砖（亮、显宽敞，但反光易刺眼）、柔光砖（不刺眼、百搭，无主灯推荐）、哑光砖（质朴防滑、但易藏污）。厨房越来越多人用「岩板」（大板、缝少、好打理）。挑砖别只看单片，要看「花色版数」（不低于 8 版才自然）和质检报告（吸水率、耐磨）。',
                        'options' => ['亮光砖', '柔光砖', '哑光砖', '岩板', '还没概念'],
                        'option_notes' => ['采光好、想要通透奢华感；卫生间地面注意防滑', '亮不刺眼、最百搭，无主灯设计推荐', '质朴防滑、适合原木日式，注意及时清洁', '大板缝少、颜值高好打理，价格也更高', '看完讲解再定也不迟'],
                    ],
                    [
                        'key' => 'floor_material', 'text' => '地面主要想铺什么？', 'type' => 'single',
                        'note' => '瓷砖耐用好打理、配地暖导热快；木地板脚感暖但怕水怕潮；不少人客厅铺砖、卧室铺木地板。铺木地板前地面要找平、还要预留和瓷砖的高度差；对平整度要求高的用「自流平」（更平但更贵）。',
                        'options' => ['全屋瓷砖', '全屋木地板', '客厅瓷砖 + 卧室木地板', '还没定'],
                        'option_notes' => ['好打理、耐用、配地暖升温快', '脚感暖、显温馨，注意防潮和环保等级', '最常见的折中，兼顾好打理和脚感', '按空间用途再定也不迟'],
                    ],
                    [
                        'key' => 'seam', 'text' => '瓷砖美缝，你有概念吗？', 'type' => 'single',
                        'note' => '美缝在瓷砖干透后、定制柜进场前做，作用是遮砖缝、防脏防霉。材料由次到好：勾缝剂（水泥、易发黑）< 美缝剂（树脂、有光泽）< 真瓷胶（环氧、防霉）< 环氧彩砂（最强、哑光百搭）。工艺上「水平式」比「凹槽式」更平整、不易藏污（但费工）。认准环保 GB18583——劣质美缝含壬基酚，比甲醛还伤（影响生育）。',
                        'options' => ['想用好的（真瓷胶 / 环氧彩砂）', '普通美缝剂够用', '还没了解'],
                        'option_notes' => ['防霉耐脏、颜值高，卫生间尤其值得', '够用就好，但认准环保、别用小作坊货', '记住一句：认环保 GB18583 + 尽量选水平式'],
                    ],
                ],
            ],
            [
                'key' => 'kitchen_bath',
                'title' => '厨房 · 卫生间',
                'intro' => '厨卫是硬装的「重灾区」——水电、防水、瓷砖、吊顶全挤在这，布局和洁具型号要趁早定，好让水电预留到位。',
                'questions' => [
                    [
                        'key' => 'kitchen', 'text' => '厨房布局倾向？', 'type' => 'single',
                        'note' => '开放式显大、动线好，但爆炒油烟要靠大吸力烟机（或加玻璃移门做半开放）。台面主流石英石（耐用抗污）和岩板（颜值高、可一体成型）；水槽推荐大单槽 + 台下盆（好清理）。橱柜属于全屋定制，但布局和嵌入式电器（冰箱、蒸烤箱、洗碗机）要在硬装阶段定好尺寸、留好水电。',
                        'options' => ['开放式 / 带岛台', '半开放（玻璃移门）', '封闭独立厨房', '还没定'],
                        'option_notes' => ['颜值和社交感强，重油烟家庭慎选或配强吸烟机', '兼顾通透和挡油烟，折中之选', '挡油烟最好，中式重油烟家庭稳妥', '按做饭习惯再定'],
                    ],
                    [
                        'key' => 'bathroom', 'text' => '卫生间想要哪些？（可多选）', 'type' => 'multi',
                        'note' => '干湿分离（淋浴房 / 玻璃隔断）更干爽、好打理；台盆下水推荐「90 度墙排」（美观、少占空间，配 P 型下水防堵）；马桶分普通 / 智能 / 壁挂——壁挂马桶悬空好扫地、更简约，但要预埋隐藏水箱、对墙体有要求，得在硬装阶段定。另外：下沉式卫生间要先回填（用陶粒或发泡水泥，别用建渣）；入墙花洒、角阀这些五金要在贴砖前定好、预埋到位。',
                        'options' => ['干湿分离', '智能马桶', '壁挂马桶（需预埋水箱）', '台盆墙排下水', '浴缸', '还没想好'],
                        'option_notes' => [
                            '淋浴区隔开，防潮防滑、好打理',
                            '带冲洗烘干，插座要提前留防溅款',
                            '悬空好扫地、更简约，但施工要求高、要预埋',
                            '90 度墙排 + P 型下水，美观又防堵',
                            '泡澡需求，注意上下水和承重',
                            '看空间和使用习惯再定',
                        ],
                    ],
                ],
            ],
            [
                'key' => 'surfaces',
                'title' => '吊顶 · 墙面 · 门',
                'intro' => '吊顶、墙面、门是硬装里最影响「颜值」的部分，也和灯光、收纳强相关。',
                'questions' => [
                    [
                        'key' => 'ceiling', 'text' => '客厅 / 主空间的吊顶想怎么做？', 'type' => 'single',
                        'note' => '吊顶的作用是藏管线（中央空调、新风）、藏灯、走造型，代价是降层高——造型越复杂降得越多。层高有限就少吊或不吊；厨卫另说，必须吊顶藏管线。想做无主灯，一般得配大平顶或边吊来藏灯带、筒灯。龙骨优先国标轻钢、厨卫石膏板用防水的；做吊顶时顺手留好窗帘盒，电动窗帘水电阶段先留电位。',
                        'options' => ['不吊顶（只刮平顶）', '极简石膏线（走一圈线条）', '双眼皮吊顶（极窄两级边）', '边吊（四周一圈，藏空调 / 灯带）', '悬浮吊顶（四周留缝 + 灯带）', '大平顶（整面吊平）', '回型吊顶（回字多级层次）', '还没定'],
                        'option_notes' => [
                            '最省层高和钱、顶面简约，但藏不了梁和管线',
                            '几乎不占层高，一圈线条添点精致，性价比高',
                            '沿墙两道极窄小台阶、能藏点灯带，几乎不压层高',
                            '四周吊一圈、中间留平，最能藏中央空调内机和风口，客厅最常见',
                            '顶面像浮起来一层、缝里打灯带，氛围感强，略费层高',
                            '整面吊平、藏净所有管线，最平整，无主灯的理想搭配，但最占层高',
                            '回字形多级线条，层次感强、偏传统，工艺和造价更高',
                            '看层高、风格和要不要藏中央空调再定',
                        ],
                    ],
                    [
                        'key' => 'wall_leveling', 'text' => '墙面找平，你想做到什么程度？', 'type' => 'single',
                        'note' => '墙面平不平影响很大——柜子、家具靠墙有没有缝，无主灯的光打上去有没有阴影，全看它。工艺分两种：「顺平」（顺着墙面找平，墙歪它还歪，最主流、便宜）和「冲筋找平 / 归方找平」（把墙与墙、墙与地顶都找到方正垂直，效果好但更贵）。要做无主灯、或对柜体贴合要求高，建议上冲筋找平。另外，墙够平还能做「无踢脚线」或极窄踢脚线更简约（踢脚线本是用来遮墙地缝隙、防踢脏的）。',
                        'options' => ['普通顺平就行', '要做冲筋找平 / 归方（更平整）', '还没概念'],
                        'option_notes' => ['预算够用、大多数家庭的选择', '想要柜体严丝合缝、或做无主灯，值得加这个钱', '记住一句：无主灯、高要求就上冲筋找平'],
                    ],
                    [
                        'key' => 'wall_finish', 'text' => '墙面主要用什么？', 'type' => 'single',
                        'note' => '主流是乳胶漆（性价比高，认准新国标 GB18582-2020、选优等品、要刷底漆）；艺术漆（有肌理质感、更贵）；墙布 / 墙纸（花色多、怕潮易翘）；护墙板（高级但贵）。记住「七分腻子三分漆」——底层腻子的环保和平整更关键，别让工人往腻子里加胶水、滑石粉。',
                        'options' => ['乳胶漆（百搭实惠）', '艺术漆（有质感）', '墙布 / 墙纸', '护墙板', '混搭 / 还没定'],
                        'option_notes' => ['最主流，认准新国标 + 优等品 + 刷底漆', '肌理质感强，造价更高', '花色丰富，注意防潮和环保', '质感高级，预算充足再考虑', '常见做法：大面积乳胶漆 + 局部点缀'],
                    ],
                    [
                        'key' => 'tv_wall', 'text' => '电视 / 沙发背景墙要做造型吗？', 'type' => 'single',
                        'note' => '背景墙分两种：只刷漆 + 电视柜 + 挂饰，属于软装、随时能改；做「造型背景墙」（岩板 / 大理石上墙、木饰面、护墙板、石膏板造型 + 灯槽）则是硬装——要木工打底（欧松板）、墙面处理，电视位要居中预埋线管（电源 + 网线 + HDMI）、把电线藏进墙里。要做造型，这些都得在硬装阶段定好、留到位。',
                        'options' => ['做造型背景墙（岩板 / 木饰面 / 护墙板等）', '简单刷漆 + 电视柜（归软装）', '投影 / 不要电视墙', '还没定'],
                        'option_notes' => [
                            '硬装打底 + 水电预埋要提前定，装完难改',
                            '最省事灵活，以后想换随时换',
                            '投影要预埋线管到顶 / 到沙发后，幕布或抗光墙也要提前定',
                            '先把电视位的电源和线管居中预留出来，进可攻退可守',
                        ],
                    ],
                    [
                        'key' => 'door_material', 'text' => '室内门想用哪种？', 'type' => 'single',
                        'note' => '实木复合门（内芯多为密度板，便宜但甲醛隐患较大）、实木门（带防变形结构，环保和质感均衡，主流推荐）、原木门（全实木最环保，但贵且易开裂）。门尽量做高、甚至通顶，更显大气；买门看样式不必迷信大牌。',
                        'options' => ['实木复合门', '实木门', '原木门', '还没定'],
                        'option_notes' => ['价格低，但密度板占比大、环保要留意', '防变形、环保质感均衡，性价比之选', '最环保但最贵、易开裂，预算足再上', '看预算和环保要求再定'],
                    ],
                ],
            ],
            [
                'key' => 'eco_budget',
                'title' => '环保与预算',
                'intro' => '硬装用料多、又封在墙里，环保和预算最好一开始就有个底。',
                'questions' => [
                    [
                        'key' => 'eco_care', 'text' => '你对环保（甲醛）有多在意？', 'type' => 'single',
                        'note' => '装修污染不止甲醛。甲醛名气最大，是因为它释放周期特别长（几年到十几年）、几乎所有人造板都有，而不是毒性最强——论毒性，苯是明确致白血病的一类致癌物（藏在油漆、胶水里，只是挥发快）、氡是放射性气体（藏在天然石材、部分瓷砖里，致肺癌仅次于吸烟），劣质美缝里的壬基酚还影响生育。所以别只盯甲醛。认准认证：板材看 ENF 级，腻子 / 乳胶漆看新国标 GB18582-2020 和十环 / 法国 A+，美缝看 GB18583，石材瓷砖认准放射性 A 类。家里有小孩、老人尤其要卡等级。',
                        'options' => ['很在意，愿为环保加预算', '一般，达标就行', '主要看预算'],
                        'option_notes' => ['优先 ENF 板材 + 高等级认证主材，多种叠加也放心', '认准新国标合格线，别用三无材料', '至少守住新国标底线，别贪便宜买路边货'],
                    ],
                    [
                        'key' => 'budget', 'text' => '硬装这块预算大概？', 'type' => 'single',
                        'note' => '硬装（不含家电、家具、软装）钱主要花在隐蔽工程和主材上，丰俭差距很大。找装修公司分半包（人工 + 辅料，主材自己买）、全包（含主材）、清包（只出人工）。隐蔽工程别省，面子工程可退让。',
                        'options' => ['越省越好', '中等，钱花在隐蔽工程上', '预算充足，一步到位', '还没概念'],
                        'option_notes' => ['守底线：水电、防水、环保不能省', '聪明的分配：把钱砸在看不见但最要紧的地方', '主材和工艺都上更好的档次', '看完区间和需求再估一个'],
                    ],
                ],
            ],
            [
                'key' => 'pitfalls',
                'title' => '最后，给自己一份避坑清单',
                'intro' => '硬装的坑大多埋在看不见的地方。收藏这几条，施工验收时自己照着一项项对，就不容易被坑。',
                'questions' => [
                    [
                        'key' => 'watch_points', 'text' => '这些硬装的坑，哪些你想重点记一记？（可多选）', 'type' => 'multi',
                        'note' => '这几条覆盖了硬装最常见、后果也最严重的问题，看懂它们，你就心里有底了。',
                        'options' => [
                            '承重墙绝不能拆、新建墙要挂网',
                            '水电做完要「打压测试」+ 拍照留档',
                            '防水做完必须 48 小时闭水试验',
                            '腻子只能加水，不加胶水 / 滑石粉',
                            '瓷砖等干透再验空鼓、别急着美缝',
                            '隐蔽工程每步验收合格再进下一道',
                        ],
                        'option_notes' => [
                            '拆承重墙危害整栋楼；新旧墙交接不挂网必开裂',
                            '水管打压保压、电路拍照留位置，后期维修和加装都靠它',
                            '不做闭水试验，漏水要砸砖重来，代价极大',
                            '801 胶含甲醛、滑石粉致癌，工人图省事爱加，务必盯住',
                            '水泥没干透验不出空鼓，美缝也会脱落发霉',
                            '水电、防水、瓦工、木工每道都验收，问题别带到下一步',
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
