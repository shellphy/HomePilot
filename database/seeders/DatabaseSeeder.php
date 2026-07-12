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
        $this->centralAcPrimerCensus();
        $this->customFurnitureCensus();
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
                        'note' => '按现在的了解选就行，不影响后续作答。',
                        'options' => ['完全没概念，第一次了解', '听过一些名词，但不懂工艺', '做过一些功课', '已经比较清楚了'],
                        'option_notes' => ['正好，跟着往下答就行', '这份问卷帮你把名词背后的门道串起来', '看看有没有补充的盲区', '当复习，也给拿不准的邻居留个参考'],
                    ],
                    [
                        'key' => 'worries', 'text' => '硬装这块，你最担心 / 最想搞懂什么？（可多选）', 'type' => 'multi',
                        'note' => '选出最在意的事，后面重点看这些题。',
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
                        'key' => 'handover_check', 'text' => '开工前，原房质量检查做了吗？', 'type' => 'single',
                        'note' => '开工前检查空鼓、开裂、渗漏和排水并留档，保修期内的问题先报修。',
                        'options' => ['已验房并留档', '简单看过，还没系统检查', '还没验房'],
                        'option_notes' => ['把问题清单和照片交给施工方，开工前确认处理边界', '开工前补查空鼓、渗漏和排水，避免责任说不清', '先验房再拆改，能由开发商整改的问题不要自己买单'],
                    ],
                    [
                        'key' => 'trade_coordination', 'text' => '水电定位前，需要到场联合交底的设备定了吗？（可多选）', 'type' => 'multi',
                        'note' => '这些设备会共用吊顶和墙地空间，水电开槽前应按同一张图联合交底。',
                        'options' => ['中央空调 / 新风', '地暖 / 暖气', '热水 / 净水系统', '橱柜和嵌入式电器', '智能家居 / 网络', '暂时都没定'],
                        'option_notes' => ['确认内机、风口、冷凝水、梁位和检修空间', '确认分集水器、壁挂炉、温控和地面完成高度', '确认设备尺寸、回水方案、上下水和电源', '先定冰箱、洗碗机、蒸烤箱等尺寸和接口', '确认弱电箱、网线、零线、传感器和控制方式', '至少先列设备清单，未定型号也要预留合理条件'],
                    ],
                    [
                        'key' => 'demolition', 'text' => '你家要拆改墙体、改户型吗？', 'type' => 'single',
                        'note' => '主体结构不得擅自拆改，以结构图和审批为准；墙体交接处按方案做抗裂处理。',
                        'options' => ['大改（拆墙 + 新建，重塑格局）', '小改（拆个非承重墙 / 垭口）', '基本不动', '还没想好'],
                        'option_notes' => ['先让设计师对着结构图确认哪些能拆', '非承重墙可动，交接处记得挂网', '省心省钱，原格局够用就不折腾', '量房时对着图纸一起定'],
                    ],
                    [
                        'key' => 'points', 'text' => '插座、开关点位你心里有数吗？', 'type' => 'single',
                        'note' => '先按家电和使用场景排点位、回路；线径和空开按负荷与规范确定。',
                        'options' => ['已经按家电清单列过', '大概知道要多留几个', '完全没头绪'],
                        'option_notes' => ['最靠谱：先定家电型号再排点位', '重点多留：厨房台面、床头、沙发、餐桌下', '照「每个电器 + 备用」的思路数一遍就有了'],
                    ],
                    [
                        'key' => 'water_extras', 'text' => '这些系统要不要提前预留？（可多选，都得在水电阶段定）', 'type' => 'multi',
                        'note' => '这些系统要在水电阶段留管线和电位，后补成本很高。',
                        'options' => ['前置 / 中央净水', '软水机', '直饮水（RO 净水机）', '零冷水热水', '中央吸尘系统', '暂不考虑'],
                        'option_notes' => [
                            '前置滤大颗粒杂质、中央净水改善全屋水质',
                            '软化水质，洗澡不涩、少水垢，护热水器和皮肤',
                            '厨房末端装 RO，出直饮水，可接管线机 / 冰箱',
                            '通过热水循环缩短等待时间，需提前规划回水管或适配的循环方案',
                            '插座式吸口预埋墙里，打扫不用拖着主机',
                            '简单省事，后面想上再看',
                        ],
                    ],
                    [
                        'key' => 'smart_wiring', 'text' => '网络、智能这些弱电要不要提前布？（可多选）', 'type' => 'multi',
                        'note' => '网络和智能设备也要提前布线；重点确认网线、零线、门口设备和安防电源。',
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
                        'note' => '地暖影响层高和地面做法，暖气片占墙面；取暖方式要在水电和铺地前确定。',
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
                        'note' => '换窗要综合看性能和安装；封阳台先确认规划与物业要求，洗衣区提前留水电。',
                        'options' => ['换断桥铝窗（隔音隔热）', '封阳台（扩空间）', '阳台做洗衣 / 家务区', '原窗够用、不动', '还没定'],
                        'option_notes' => [
                            '临街、西晒、隔音差的值得换；认准型材壁厚和玻璃配置',
                            '扩出使用面积，注意防水和荷载，别拆到承重',
                            '要提前留上下水、防水和插座',
                            '省一笔；原窗不漏、不隔音差就够用',
                            '换窗趁早，它影响后面所有工序',
                        ],
                    ],
                    [
                        'key' => 'soundproofing', 'text' => '哪些地方需要重点做隔音？（可多选）', 'type' => 'multi',
                        'note' => '先判断噪声来自窗、邻墙还是管道，再针对声源处理；单贴薄棉通常不够。',
                        'options' => ['临街窗户', '卧室相邻墙', '卫生间排水管', '入户门', '暂无明显噪声'],
                        'option_notes' => ['先改善密封，再按噪声频段选中空或夹胶等玻璃配置', '结构要避免刚性声桥，施工会占少量室内尺寸', '包管同时处理吊顶和穿墙缝，别封死检修口', '关注门扇密度、四周密封和门底缝', '不必为没有的问题堆材料，先实地听噪声'],
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
                        'note' => '防水范围按空间和规范确定，重点处理管根、墙角和门口；完成后必须检验。',
                        'options' => ['知道要做闭水试验', '只知道要做防水', '完全不懂'],
                        'option_notes' => ['很好，验收时记录检验范围、时长和结果', '记住关键动作是按规范完成闭水或淋水检验', '先按设计做好节点和范围，再通过检验确认效果'],
                    ],
                    [
                        'key' => 'tile_finish', 'text' => '瓷砖表面你偏好哪种？', 'type' => 'single',
                        'note' => '光泽只代表观感，不等于防滑或耐污；湿区还要单独看防滑指标。',
                        'options' => ['亮光砖', '柔光砖', '哑光砖', '不同空间分别选', '还没概念'],
                        'option_notes' => ['显亮通透，但强光下反射明显；湿区仍要看防滑指标', '观感柔和，选购时实际看灯光下的反射和防污', '质感克制，但不能仅凭“哑光”判断防滑和耐污', '按客餐厅、厨房、卫生间的采光和使用条件分别选更稳妥', '先看样板铺贴效果和检测指标再定'],
                    ],
                    [
                        'key' => 'floor_material', 'text' => '地面主要想铺什么？', 'type' => 'single',
                        'note' => '瓷砖耐用，木地板脚感暖；混铺要提前处理找平和完成面高差。',
                        'options' => ['全屋瓷砖', '全屋木地板', '客厅瓷砖 + 卧室木地板', '还没定'],
                        'option_notes' => ['好打理、耐用、配地暖升温快', '脚感暖、显温馨，注意防潮和环保等级', '最常见的折中，兼顾好打理和脚感', '按空间用途再定也不迟'],
                    ],
                    [
                        'key' => 'tile_layout_acceptance', 'text' => '贴砖前后，哪些细节准备重点确认？（可多选）', 'type' => 'multi',
                        'note' => '贴前确认排版、收口和地漏坡向；贴后检查平整、空鼓并做通水测试。',
                        'options' => ['先确认排版和窄条位置', '地漏坡向与排水速度', '墙地砖通缝和收口', '空鼓与平整度', '铺完后的保护', '交给师傅决定'],
                        'option_notes' => ['门口和显眼位置尽量避免过窄切条，开工前看图比返工便宜', '现场泼水或通水确认不积水、不倒坡，地漏位置也要便于维护', '提前统一砖缝、阳角、门槛和不同材料交接做法', '按适用验收标准抽查，发现问题先判断范围和原因再整改', '达到条件后及时保护，避免砂粒、交叉施工和搬运划伤砖面', '至少把排版、坡度和验收标准书面确认，别只靠口头习惯'],
                    ],
                    [
                        'key' => 'seam', 'text' => '瓷砖美缝，你有概念吗？', 'type' => 'single',
                        'note' => '美缝材料没有简单高低排名，要按干湿环境、砖面、耐污需求和可维修性选择。',
                        'options' => ['想用好的（真瓷胶 / 环氧彩砂）', '普通美缝剂够用', '还没了解'],
                        'option_notes' => ['按空间选正规产品，先在边角试色、试清洁和确认是否伤砖面', '干区可以按预算选择，但仍要核对产品标准和施工条件', '先确认砖缝宽度、砖面类型和使用环境，再选材料与收口效果'],
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
                        'note' => '布局决定电器、水电和排烟位置；开放式还要符合燃气与消防要求。',
                        'options' => ['开放式 / 带岛台', '半开放（玻璃移门）', '封闭独立厨房', '还没定'],
                        'option_notes' => ['颜值和社交感强，重油烟家庭慎选或配强吸烟机', '兼顾通透和挡油烟，折中之选', '挡油烟最好，中式重油烟家庭稳妥', '按做饭习惯再定'],
                    ],
                    [
                        'key' => 'bathroom', 'text' => '卫生间想要哪些？（可多选）', 'type' => 'multi',
                        'note' => '干湿分离、墙排和入墙洁具都要提前核对尺寸、排水、预埋与检修条件。',
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
                        'key' => 'lighting', 'text' => '照明想按哪些使用场景来规划？（可多选）', 'type' => 'multi',
                        'note' => '灯具可后买，但灯位、回路、开关和调光方式要在水电阶段确定。',
                        'options' => ['基础主照明', '阅读 / 办公', '用餐', '观影 / 氛围', '夜间起夜', '智能调光'],
                        'option_notes' => ['保证日常活动和清洁所需亮度，别只做氛围光', '书桌、床头等任务区要单独补光并控制眩光', '餐桌灯位先跟桌子最终位置对齐', '和主照明分回路，避免看电视时全屋过亮', '走廊、床下或卫生间可用低位感应灯减少刺眼', '提前确认调光协议、零线和驱动是否兼容'],
                    ],
                    [
                        'key' => 'ceiling', 'text' => '客厅 / 主空间的吊顶想怎么做？', 'type' => 'single',
                        'note' => '吊顶会降低净高；先协调梁、风口、灯位、柜门和窗帘盒，并留好检修口。',
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
                        'note' => '顺平更省钱，归方更利于柜体、门套和洗墙灯贴合，可只提高重点墙面。',
                        'options' => ['普通顺平就行', '要做冲筋找平 / 归方（更平整）', '还没概念'],
                        'option_notes' => ['预算友好，是大多数墙面的常规选择', '想要柜体贴合、窄踢脚线或洗墙灯效果，重点部位可提高要求', '先按柜体、门套、灯光等交接要求决定哪些墙需要找方'],
                    ],
                    [
                        'key' => 'wall_finish', 'text' => '墙面主要用什么？', 'type' => 'single',
                        'note' => '面层效果先看基层和平整度；材料按配套体系施工，不现场乱加胶或粉料。',
                        'options' => ['乳胶漆（百搭实惠）', '艺术漆（有质感）', '墙布 / 墙纸', '护墙板', '混搭 / 还没定'],
                        'option_notes' => ['最主流，认准新国标 + 优等品 + 刷底漆', '肌理质感强，造价更高', '花色丰富，注意防潮和环保', '质感高级，预算充足再考虑', '常见做法：大面积乳胶漆 + 局部点缀'],
                    ],
                    [
                        'key' => 'tv_wall', 'text' => '电视 / 沙发背景墙要做造型吗？', 'type' => 'single',
                        'note' => '造型背景墙属于硬装，要提前定打底和线管；只刷漆则更灵活。',
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
                        'note' => '门的名称不等于内部结构，重点核对门芯、饰面、环保、隔音、五金和安装。',
                        'options' => ['实木复合门', '实木门', '原木门', '还没定'],
                        'option_notes' => ['常见且价格跨度大，重点核对门芯、饰面、封边和检测报告', '用料和结构差异很大，不能只凭名称判断稳定性', '天然木材质感好，但更考验含水率控制和使用环境', '按隔音、环保、耐用、造型和预算综合比较'],
                    ],
                ],
            ],
            [
                'key' => 'eco_budget',
                'title' => '装修公司 · 环保 · 预算',
                'intro' => '找谁做、用什么料、花多少钱——这三件事最好一开始就有个底。',
                'questions' => [
                    [
                        'key' => 'eco_care', 'text' => '你对环保（甲醛）有多在意？', 'type' => 'single',
                        'note' => '环保不只看甲醛或气味，要核对对应标准、检测报告和全屋材料叠加量。',
                        'options' => ['很在意，愿为环保加预算', '一般，达标就行', '主要看预算'],
                        'option_notes' => ['优先 ENF 板材 + 高等级认证主材，多种叠加也放心', '认准新国标合格线，别用三无材料', '至少守住新国标底线，别贪便宜买路边货'],
                    ],
                    [
                        'key' => 'contract_mode', 'text' => '装修打算找谁做？大概什么价位？', 'type' => 'single',
                        'note' => '全包省心、半包可控、清包最费精力；比价前先统一项目范围和计价面积。',
                        'options' => ['整装 / 全包公司（省心）', '半包（主材自己买、控品牌）', '清包（只请工人、最省）', '游击队 / 熟人队（便宜有风险）', '还没定'],
                        'option_notes' => [
                            '一站式省心，重点核报价有没有漏项、主材是什么品牌型号',
                            '性价比之选、能控主材品牌，但要自己盯材料进场',
                            '最省钱但最操心，适合懂行、有时间的',
                            '省钱但没保障，尽量签合同、留凭证，慎选',
                            '先看完模式和价位再定',
                        ],
                    ],
                    [
                        'key' => 'budget', 'text' => '硬装这块预算大概？', 'type' => 'single',
                        'note' => '先明确预算是否含主材，把钱优先留给安全、隐蔽工程和高频使用处。',
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
                        'note' => '选出你最想提醒自己的验收和合同重点。',
                        'options' => [
                            '主体结构不擅自拆改、墙体交接按方案做抗裂处理',
                            '水电做完要「打压测试」+ 拍照留档',
                            '防水完成后按规范做闭水 / 淋水检验',
                            '腻子和涂料按产品体系施工，不乱加材料',
                            '瓷砖等干透再验空鼓、别急着美缝',
                            '隐蔽工程每步验收合格再进下一道',
                            '报价单核对：防漏项、材料规格写清楚',
                            '计价面积口径：套内还是建筑、含不含墙体、露台不算',
                            '材料进场对合同，隐蔽前拍照留档',
                            '门窗、电梯、公共区域和已完工面做好保护',
                        ],
                        'option_notes' => [
                            '结构安全不能靠经验判断；拆改前核对结构图并完成审批',
                            '水管打压保压、电路拍照留位置，后期维修和加装都靠它',
                            '按材料养护要求完成检验并和楼下共同确认，记录水位和时间',
                            '现场随意加胶或粉料会改变性能并带来环保风险，按说明书和配套体系施工',
                            '水泥没干透验不出空鼓，美缝也会脱落发霉',
                            '水电、防水、瓦工、木工每道都验收，问题别带到下一步',
                            '签合同前逐项核对施工范围，把材料品牌、型号、规格、数量、损耗和增项单价写进报价单',
                            '面积口径直接决定总价：建筑面积比套内贵一截、套内还分含不含墙体；咱们小区四代住宅的露台不能封、属室外、装修公司不施工，务必别算进计价面积',
                            '电线、水管、防水等进场时核对品牌型号和批次，封槽封板前记录管线走向及检验结果',
                            '保护范围、材料、维护和损坏责任提前约定；保护层里的砂粒也可能划伤完成面',
                        ],
                    ],
                ],
            ],
        ];
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
                'purpose' => '第一次装修、完全没接触过中央空调？这份问卷把该懂的都串成了题：每题有讲解、选项下写清「为什么」，答一遍 5 分钟就能建立基本认知。纯科普、不推销。',
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
                        'note' => '这俩差在室内「用什么传冷热」。氟机（多联机）室内走冷媒：制冷快、系统成熟、性价比高，是绝大多数家庭的选择，缺点是出风偏凉偏干——高端氟机用「三管制」热回收（相比常规「两管制」）能给冷风回点温、没那么凉，不同房间还能同时冷热，但更贵。水机室内走水：出风柔和、不干燥、体感更舒服，适合追求舒适或大面积，缺点是造价高、制冷稍慢。',
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
                'title' => '一拖几、外机匹数、厨房',
                'intro' => '报价单看着吓人，配置核心就两件事：装几个内机（一拖几）、外机要多大（多少匹）。顺带把厨房怎么办也捋一下。',
                'questions' => [
                    [
                        'key' => 'internal_units', 'text' => '打算装几个内机？（客厅和每个卧室各算一个）', 'type' => 'single', 'required' => true,
                        'note' => '内机数 = 客厅（含餐厅）算 1 个 + 每间卧室各 1 个。咱们小区 107㎡ 是 3 房、130㎡ 和 154㎡ 都是 4 房，照这个数：107㎡ 满装通常一拖四，130㎡ / 154㎡ 满装通常一拖五。客厅要是长条形，内机得选「高静压」的，风才送得到最里头。内机除了数量也有花样：双出风（目前美的独有，下出风制热更均匀）、3D 出风（送风更立体、少直吹）、除菌内机（带杀菌净化），按需选、不必全上。内机怎么出风、回风也有讲究（如「下出侧回」比出回风挤在一起更不容易气流短路）。',
                        'options' => ['3 个（一拖三）', '4 个（一拖四）', '5 个及以上（一拖五 / 六）', '还没数过'],
                        'option_notes' => ['只装客厅和主要卧室、想省一点的做法', '107㎡（3 房）满装的常见配置', '130㎡ / 154㎡（4 房）满装；客厅大想分区会更多', '照「客厅 + 卧室数」数一下，或等量房时定'],
                    ],
                    [
                        'key' => 'same_time', 'text' => '家里通常几个房间会同时开空调？', 'type' => 'single',
                        'note' => '内机总功率可以超过外机（行话「超配」，一般到 125%~130%）。外机型号里的数字就是制冷量：140 约 5 匹、160 约 6 匹、180 约 7 匹。一拖四常配 140~160、一拖五常配 160~180；同开的房间越多，外机数字要越大，否则「带不动」、越用越不凉。外机除了匹数，还看用料和散热：好外机的换热器是「双排 / 双排半」（散热面积大、更省电、低温更给力），钣金、防腐这些「堆料」也影响耐用。',
                        'options' => ['各屋独立，很少同时开', '客厅 + 主卧常一起开', '全家几乎同时开'],
                        'option_notes' => ['可以适当超配，外机选小一档省钱', '按主要生活动线配，最常见', '外机匹数要给足，别被最低价的小一号外机忽悠'],
                    ],
                    [
                        'key' => 'kitchen_ac', 'text' => '厨房要不要单独解决制冷？', 'type' => 'single',
                        'note' => '中央空调的内机一般不进厨房——油烟会糊住内机和滤网、又难清洗，所以厨房不算在「一拖几」里。厨房夏天闷热，常见做法是单独装一台「厨房专用空调」（防油烟、滤网可水洗），或退一步装个「凉霸」（藏在集成吊顶里的电风扇，只吹风不制冷、便宜）。要装厨房空调，插座和挂位也得装修前留好。',
                        'options' => ['要，装厨房专用空调（能制冷）', '装个凉霸就行（只吹风、便宜）', '不用，厨房不怕热', '还没想好'],
                        'option_notes' => [
                            '防油烟机型、滤网可水洗；插座和挂位装修前留好',
                            '集成吊顶里的电风扇，只吹风不制冷，便宜省事',
                            '做饭少、或油烟机通风强就够',
                            '答完再定，先把厨房的电位留出来',
                        ],
                    ],
                ],
            ],
            [
                'key' => 'combo',
                'title' => '冬天取暖 · 新风',
                'intro' => '中央空调主要管夏天制冷。冬天要不要地暖、要不要顺带解决洗澡热水和通风换气——这些管路都得在装修前预留，后补是拆吊顶、刨地面的大工程，最好一起定。',
                'questions' => [
                    [
                        'key' => 'heating', 'text' => '冬天打算怎么取暖？', 'type' => 'single',
                        'note' => '南方没有集中供暖，冬天取暖得自己装，主流有这么几条路：① 空调制热——直接用中央空调吹热风，最省钱，但热风上飘、脚下偏凉也偏干；② 独立地暖——单独装燃气壁挂炉带水地暖，从脚下往上暖、最舒服，壁挂炉还顺带供洗澡热水，是南方装修的主流；③ 暖气片——也靠壁挂炉，但走暖气片，升温快、能后期明装，占墙面、体感略逊地暖；④ 电地暖——用电发热，装着简单，适合卫生间等小面积，大面积电费偏高；⑤ 两联供 / 三联供——一台空气源热泵同时管制冷 + 地暖（+ 热水），省一套主机，细分天氟地水、天水地水。',
                        'options' => ['空调制热就够（不另外装）', '独立地暖（燃气壁挂炉 + 水地暖）', '暖气片（壁挂炉 + 暖气片）', '电地暖（卫生间 / 小面积）', '两联供 / 三联供（空调和地暖一套系统）', '还没想好'],
                        'option_notes' => [
                            '预算最省；制热强的空调（带「喷气增焓」）冬天基本够用，有地暖会更暖脚、不干、更舒服',
                            '最舒服、也最主流；壁挂炉同时供地暖和洗澡热水——优先「冷凝式」更省燃气、功率按采暖面积选。品牌进口 / 合资有博世、菲斯曼、威能、林内、阿里斯顿、能率、法罗力、贝雷塔，国产有小松鼠、小沃、海尔等；壁挂炉位和管路要装修前预留',
                            '升温快、后期也能明装；就是占墙面、体感不如地暖',
                            '安装简单、适合局部；大面积长期开电费偏高',
                            '一套主机兼顾冷暖（+ 热水）、省地方；天氟地水偏省、天水地水更柔和',
                            '答完再定也不迟，先让施工把地暖 / 壁挂炉的管路和点位预留出来',
                        ],
                    ],
                    [
                        'key' => 'fresh_air', 'text' => '要不要装新风系统？', 'type' => 'single',
                        'note' => '新风 = 关着窗也能持续把室外空气过滤后送进来、把脏空气和二氧化碳排出去。好的是「双向流全热交换」——一边送一边排，还回收室内温度、不浪费空调。布局讲「几进几出」：每间卧室、客厅各一个送风口（进），卫生间、走廊设排风口（出），进出的数量和位置决定换气均不均匀。不少机型还带过滤 PM2.5、除湿、紫外线杀菌。注意：管道新风要走一整套吊顶管路，必须在吊顶前定下来、随空调一起施工——装完没法「留个位置」以后补。',
                        'options' => ['要装（在意空气质量 / 长期关窗）', '不装（靠开窗通风）', '还没想好'],
                        'option_notes' => [
                            '优先「双向流全热交换」；关注滤芯等级、有没有除湿和杀菌、「几进几出」是否覆盖到每个房间',
                            '简单省钱，靠开窗，或后期加壁挂式新风 / 净化器 / 除湿机补',
                            '那趁吊顶前尽快定——管道新风得随空调一次布好，事后补不了',
                        ],
                    ],
                ],
            ],
            [
                'key' => 'machine_cost',
                'title' => '性能舒适、品牌与预算',
                'intro' => '这一节把「机器素质」和「花多少钱」一起过一遍。性能、舒适功能是高端机和入门机拉开差距、也是加钱的地方；品牌大致分进口、国产两档，差价约三四成。',
                'questions' => [
                    [
                        'key' => 'performance_care', 'text' => '选空调时，你更看重哪些性能 / 舒适功能？（可多选）', 'type' => 'multi',
                        'note' => '这些是高端机和入门机拉开差距的地方，加钱也主要加在这。看看你最在意哪几样，别为用不上的功能多花钱。',
                        'options' => [
                            '制热够强（冬天不靠地暖也暖和）',
                            '静音（卧室安静）',
                            '省电（长期电费低）',
                            '温湿平衡 / 除湿（不干不闷）',
                            '智能化（手机 App、语音、联动）',
                            '够用就行，先保基础',
                        ],
                        'option_notes' => [
                            '看压缩机带不带「喷气增焓」——低温制热更足，好的热泵机型不靠费电的「电辅热」也能暖。制热强的空调冬天能顶用，配地暖当然更舒服',
                            '看压缩机（常见双转子、双摆、涡旋，涡旋更静更稳）、外机风扇（尽量选双风扇，更高效更安静，如日立 mini2 分单 / 双风扇）和内机噪音（好内机低档能到 20 分贝出头）',
                            '认准一级能效、全直流变频，看 APF（全年能效比）；能稳定「低频」运行的更省电、控温更稳，变频比定频省 20%~30%',
                            '高端机用「三管制」做温湿平衡（如大金 N+ / U+），能单独调湿度、给冷风回温，夏天除湿、冬天不干，体感最舒服',
                            '支持 App 远程、语音和智能家居联动，能定时、分房间独立控温',
                            '主流机型都能满足基本冷暖，预算有限先保证匹数和安装质量',
                        ],
                    ],
                    [
                        'key' => 'brand_lean', 'text' => '你更倾向哪一档品牌？', 'type' => 'single',
                        'note' => '氟机进口一线：大金、日立（海信日立）、三菱电机、三菱重工；国产一线：美的、格力、海尔、海信。走水机 / 两联供是另一批品牌——美系「四大」特灵、开利、约克、麦克维尔，格力、美的等也有热泵机组。技术都成熟，主要差在品牌溢价和售后网点。',
                        'options' => ['进口一线氟机（大金 / 日立 / 三菱，贵约 30%~40%）', '国产一线氟机（美的 / 格力 / 海尔 / 海信）', '水机 / 两联供（特灵 / 开利 / 约克 / 麦克维尔、格力美的等）', '不认牌子 / 还不了解，看方案和售后'],
                        'option_notes' => [
                            '大金、日立、三菱电机、三菱重工；产品成熟、口碑稳，预算充足、追求省心可以上',
                            '同样一级能效变频，价格更友好，是近年主流选择',
                            '走水机 / 两联供的另一批品牌，美系「四大」偏专业，格力 / 美的等也有热泵机组',
                            '成熟的想法：内机配置、安装工艺和售后往往比牌子更影响体验',
                        ],
                    ],
                    [
                        'key' => 'budget', 'text' => '这块预算大概多少？', 'type' => 'single',
                        'note' => '给个量级参考（2025—2026 行情）：一拖四 / 五的氟机，国产约 2.5 万~3.5 万、进口约 3.5 万~4.5 万起；水机、两联供 / 三联供更贵。中央空调基本是找本地经销商线下量房、设计、安装，不像普通家电网上下单就完事——网上多是「裸机价」，不含设计、安装、辅材，别直接拿来比。报价一定要问「这是不是全包价」。',
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
                            '「机器价」低不代表总价低，辅材和安装才是隐藏大头；排水坡度不够的位置得配「冷凝水提升泵」抽水，防吊顶漏水',
                            '外机被换成小一号最难发现，也最影响制冷效果',
                            '口头承诺无效，规格、品牌都要落到合同上；铜管加长（约每米上百元）、内机风口加长加宽都可能另收费，问清单价',
                            '串货机 / 无码机厂家不保修，要求提供可查询的正规机器码',
                            '施工外包、班组不固定最容易出工艺问题和扯皮',
                            '铜管要保压测漏、抽真空排杂气，省这步以后容易漏氟、不制冷；检修口也要留够、好打开',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * 全屋定制科普征集（装修题库之一，与硬装 / 软装拆开）：纯知识分享、不推销。
     * 面向第一次做柜子、分不清板材封边五金的业主，参考《房屋装修全流程指南》的定制章节，
     * 每题带讲解、每个选项带一句「为什么」：从「定制是什么、和木工现场的区别」到板材门板、封边五金、
     * 收纳需求、设计灯光、预算避坑，顺着答完就是一条入门路径。
     */
    private function customFurnitureCensus(): void
    {
        $modules = $this->customFurnitureModules();

        $census = Matter::factory()->create([
            'type' => 'census',
            'initiator_id' => null,
            'state' => 'open',
            'category' => '全屋定制',
            'title' => '全屋定制怎么选 · 答题即入门',
            'target_count' => 0,
            'payload' => [
                'purpose' => '第一次做柜子、分不清颗粒板多层板、也不懂封边五金？这份问卷把该懂的都串成了题：每题有讲解、选项下写清「为什么」，答一遍就能入门。纯科普、不推销。',
                'modules' => $modules,
            ],
        ]);

        $questions = collect($modules)->flatMap(fn (array $module): array => $module['questions'])->keyBy('key');
        foreach (range(1, 40) as $i) {
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
     * 全屋定制科普问卷题库：讲解写在 note、选项讲解写在 option_notes，答题即建概念。
     * 知识点取自《房屋装修全流程指南》的定制章节，为小白建立认知。
     *
     * @return array<int, array<string, mixed>>
     */
    private function customFurnitureModules(): array
    {
        return [
            [
                'key' => 'basics',
                'title' => '先认识全屋定制',
                'intro' => '全屋定制是按现场尺寸设计、生产并安装柜体。成品效果由材料、设计、加工、安装和服务共同决定。',
                'questions' => [
                    [
                        'key' => 'know_level', 'text' => '你对全屋定制了解到哪一步了？', 'type' => 'single', 'required' => true,
                        'note' => '按现在的了解选择即可，不影响后续作答。',
                        'options' => ['完全没概念，第一次了解', '听过一些名词，但不懂门道', '做过一些功课', '已经比较清楚了'],
                        'option_notes' => ['正好，跟着往下答就行', '这份问卷帮你把板材、封边、五金串起来', '看看有没有补充的盲区', '当复习，也给拿不准的邻居留个参考'],
                    ],
                    [
                        'key' => 'vs_carpenter', 'text' => '柜子想走全屋定制，还是木工现场打？', 'type' => 'single',
                        'note' => '两种方式都能做好，重点比较设计深化、加工封边、现场收口、工期、责任边界和售后。',
                        'options' => ['工厂定制', '木工现场制作', '两种结合', '还没定'],
                        'option_notes' => ['流程标准化，重点核对图纸、加工和安装能力', '现场调整灵活，重点核对材料、设备和施工能力', '复杂基层现场做，门板等由工厂加工，先明确责任边界', '带同一份需求和图纸分别报价更好比较'],
                    ],
                    [
                        'key' => 'which_cabinets', 'text' => '打算做哪些柜子？（可多选）', 'type' => 'multi',
                        'note' => '先圈定范围，设计和报价都按这个来。',
                        'options' => ['衣柜', '橱柜', '鞋柜 / 玄关柜', '餐边柜', '电视柜', '书柜 / 榻榻米', '阳台 / 家务柜', '卫浴柜'],
                        'option_notes' => [
                            '卧室大头，收纳规划最费心',
                            '厨房核心，和水电、台面强相关',
                            '进门收纳，鞋多要留活动层板',
                            '餐厅收纳 + 电器（管线机、咖啡机）',
                            '悬空更显轻盈，记得预留电源和灯带',
                            '书房 / 儿童房，榻榻米兼顾睡与储',
                            '洗衣家务动线，留好水电和地漏',
                            '潮湿环境，重点看材料耐湿、封边和通风',
                        ],
                    ],
                    [
                        'key' => 'site_readiness', 'text' => '正式复尺前，哪些现场条件需要协调？（可多选）', 'type' => 'multi',
                        'note' => '柜体尺寸会受墙地顶完成面、水电、门窗、吊顶和设备影响，复尺前应统一基准。',
                        'options' => ['墙地顶完成面', '插座和上下水', '门窗与踢脚线', '空调风口 / 地暖', '嵌入式电器尺寸', '还没开始协调'],
                        'option_notes' => ['确认找平、地板或瓷砖厚度和吊顶标高', '避免插座被柜体遮住，核对排水和检修空间', '确认柜门开启、收口和踢脚线交接', '避免柜门挡风口，并确认地暖墙面禁打孔范围', '以最终型号和安装说明复核开孔、散热与电源', '先列交底清单，再约设计和施工共同确认'],
                    ],
                ],
            ],
            [
                'key' => 'board',
                'title' => '板材与门板',
                'intro' => '板材决定环保和耐用，是全屋定制最该上心的一环；门板决定颜值。',
                'questions' => [
                    [
                        'key' => 'board_material', 'text' => '柜体板材倾向哪种？', 'type' => 'single',
                        'note' => '材料名称不能直接代表环保或耐潮，要结合基材、饰面、封边、检测报告和使用环境判断。',
                        'options' => ['刨花板 / 颗粒板', '胶合板 / 多层板', 'OSB / 欧松板', '其他植物纤维刨花板', '还没定'],
                        'option_notes' => ['尺寸稳定、饰面丰富，重点看等级和板边防潮', '层间质量差异大，重点看胶合、平整和检测报告', '定向刨花结构，关注饰面适配和加工质量', '禾香板等属于这一类，环保仍以检测结果为准', '按柜体结构、环境和预算逐项比较'],
                    ],
                    [
                        'key' => 'eco_grade', 'text' => '板材环保等级卡到哪级？', 'type' => 'single',
                        'note' => 'ENF、E0、E1 是甲醛释放限量等级，不等同于“无醛添加”；不同体系不能直接换算。',
                        'options' => ['ENF 级', 'E0 级', 'E1 级', '按空间和用量综合选择', '还没核对'],
                        'option_notes' => ['现行国标更严格等级，仍要核对产品型号与报告', '高于室内使用底线，结合全屋用量判断', '现行国标室内使用底线，不等于不合格', '卧室、大面积用板可提高等级并控制总用量', '要求商家提供同型号、同批次可核验报告'],
                    ],
                    [
                        'key' => 'door_finish', 'text' => '柜门（门板）想要什么感觉？', 'type' => 'single',
                        'note' => '门板要把基材和表面工艺分开看，再比较耐污、耐刮、修复、造型和色差。',
                        'options' => ['PET / PETG 饰面', '烤漆', '木皮 / 实木', '三聚氰胺双饰面', '还没定'],
                        'option_notes' => ['纯色效果常见，耐用性取决于膜材、基材和覆贴工艺', '颜色和造型丰富，关注磕碰修复与批次色差', '自然质感强，关注含水率、涂装和稳定性', '耐磨易打理，基材并不只限于一种板材', '带样板回家看实际灯光，并确认清洁维护方式'],
                    ],
                ],
            ],
            [
                'key' => 'detail',
                'title' => '封边与五金',
                'intro' => '封边和五金是藏在细节里的耐用度，最容易被忽视、也最容易被偷换。',
                'questions' => [
                    [
                        'key' => 'edge_banding', 'text' => '封边工艺你了解吗？', 'type' => 'single',
                        'note' => '封边主要保护板边并改善耐潮和外观；胶种只是变量之一，加工参数和板边质量同样重要。',
                        'options' => ['EVA 封边', 'PUR 封边', '激光 / 热风等无缝封边', '按部位组合', '还没核对'],
                        'option_notes' => ['成熟常见，重点看胶线、粘接和使用环境', '耐热耐湿表现通常更好，仍要看设备和工艺', '视觉胶线较弱，需核对板材与封边带是否适配', '柜体与门板可按外观、环境和预算分别约定', '样板、工艺、板件部位和验收要求都写进合同'],
                    ],
                    [
                        'key' => 'hardware', 'text' => '柜子五金准备怎么配？', 'type' => 'single',
                        'note' => '五金要按门板重量、开合频率和承重选择，品牌之外还要核对具体系列、数量和安装。',
                        'options' => ['高频和重载位置重点升级', '全屋统一较高配置', '基础配置够用', '还没定'],
                        'option_notes' => ['厨房抽屉、大门板等重点投入，性价比更高', '体验一致但预算较高，仍要核对型号和承重', '低频轻载可用，合同写清品牌、系列和数量', '先按门板重量、抽屉用途和开合方式做五金表'],
                    ],
                ],
            ],
            [
                'key' => 'kitchen_cabinet',
                'title' => '橱柜（台面 · 水槽）',
                'intro' => '橱柜要同时协调操作动线、台面、水槽、五金、设备、水电、燃气和通风。',
                'questions' => [
                    [
                        'key' => 'kitchen_workflow', 'text' => '厨房最需要按谁的习惯设计？', 'type' => 'single',
                        'note' => '台面高度、常用物位置和操作顺序应按主要做饭者及厨房尺寸确定，不宜直接套固定模板。',
                        'options' => ['一位主要做饭者', '两人经常一起做饭', '偶尔做饭、以简餐为主', '需要兼顾老人 / 儿童', '还没梳理'],
                        'option_notes' => ['按其身高和习惯确定洗、切、炒区域', '重点保证过道、双人错身和两套操作位', '减少低频配置，把空间留给高频电器和储物', '关注拿取高度、防烫、防夹和通行空间', '先记录一周做饭流程和常用物品再设计'],
                    ],
                    [
                        'key' => 'countertop', 'text' => '橱柜台面想用哪种？', 'type' => 'single',
                        'note' => '台面要比较耐污、抗冲击、接缝、修复和安装条件；任何材料都不要直接承受极端冷热冲击。',
                        'options' => ['石英石（耐用、性价比）', '岩板（颜值高、可无缝）', '不锈钢（耐用抗菌）', '还没定 / 听设计师'],
                        'option_notes' => ['常见易维护，关注树脂质量、耐污和拼接工艺', '大规格观感完整，关注边角、开孔和运输安装', '耐水易清洁，可能出现划痕、水印和鼓包感', '结合样板、使用习惯和加工售后再定'],
                    ],
                    [
                        'key' => 'sink', 'text' => '水槽你想要哪些？（可多选）', 'type' => 'multi',
                        'note' => '水槽大小和安装方式按锅具、清洁习惯及台面条件选择；附加设备要提前留电源、排水和检修空间。',
                        'options' => ['台下盆（台面易清理）', '大单槽（能放大锅）', '不锈钢水槽', '预留垃圾处理器', '还没想好'],
                        'option_notes' => ['台面易清理，安装和后期更换要求较高', '适合大锅具，仍要结合台面宽度和使用习惯', '常见耐用，表面处理不能替代正确清洁维护', '确认当地排水条件，并预留电位、开关和检修空间', '先量常用锅具，再比较单双槽和安装方式'],
                    ],
                    [
                        'key' => 'appliance_coordination', 'text' => '哪些设备要嵌入或藏进柜体？（可多选）', 'type' => 'multi',
                        'note' => '以最终型号说明书核对净尺寸、散热、开门、进排水、电源和可更换路径。',
                        'options' => ['冰箱', '洗碗机', '蒸箱 / 烤箱', '净水 / 垃圾处理器', '洗衣机 / 烘干机', '小家电高柜', '暂时没有'],
                        'option_notes' => ['核对散热、门体开启和抽屉拉出空间', '核对上下水、电源、门板和地面完成高度', '关注独立回路、散热和高温区域材料', '柜内要能检修，避免管线挤压和漏水无处发现', '核对地漏、阀门、散热和震动空间', '按实际电器尺寸、插座和通风设计活动层板', '仍可预留通用电位和可调整空间'],
                    ],
                ],
            ],
            [
                'key' => 'storage',
                'title' => '收纳需求',
                'intro' => '柜子的本质是收纳、不是造型。把你的衣物和习惯答清楚，设计师才能把每一格排到你心坎里。',
                'questions' => [
                    [
                        'key' => 'clothing_length', 'text' => '你家衣物，长衣多还是短衣多？', 'type' => 'single',
                        'note' => '先量家里最长衣物和数量，再确定长衣区与双挂区比例，不直接套固定高度。',
                        'options' => ['长衣多', '短衣、叠放多', '差不多、都有'],
                        'option_notes' => ['长衣区为主，留足垂挂高度', '多做双挂杆和叠放区，容量翻倍', '长短区搭配，最常见'],
                    ],
                    [
                        'key' => 'hang_vs_fold', 'text' => '你更习惯挂衣服还是叠衣服？', 'type' => 'single',
                        'note' => '挂放拿取直观，叠放更省空间；按真实习惯分配挂杆、层板和抽屉。',
                        'options' => ['能挂就挂（好找不皱）', '习惯叠放（省空间）', '挂叠都要'],
                        'option_notes' => ['多留挂衣区和挂杆', '多做层板和抽屉，配分隔件', '按比例分区，挂放为主叠放为辅'],
                    ],
                    [
                        'key' => 'shoes_count', 'text' => '鞋子大概多少双？', 'type' => 'single',
                        'note' => '除数量外还要量最长、最高的鞋，并考虑换季、通风和清洁。',
                        'options' => ['不多（20 双以内）', '中等（20~50 双）', '很多（50 双以上）'],
                        'option_notes' => ['基础鞋柜够用', '留活动层板，分季节收纳', '考虑玄关整墙柜或独立鞋帽间'],
                    ],
                    [
                        'key' => 'storage_extra', 'text' => '还有哪些要专门收纳的？（可多选）', 'type' => 'multi',
                        'note' => '大件、贵重物和带电设备应提前量尺寸并确认承重、电源和使用动线。',
                        'options' => ['大量箱子 / 行李', '被褥换季', '保险柜', '首饰 / 配饰（抽屉）', '脏衣篮', '手办 / 收藏展示', '清洁工具（吸尘器等）'],
                        'option_notes' => [
                            '顶部或深柜留大件储物区',
                            '柜顶做被褥区、用大格子',
                            '预埋在柜体内、留承重和电位',
                            '抽屉配分隔件、可上锁',
                            '内置或壁挂脏衣篮，动线更顺',
                            '玻璃门展示柜 + 灯带',
                            '留高深格 + 插座给电器',
                        ],
                    ],
                ],
            ],
            [
                'key' => 'design',
                'title' => '设计与灯光',
                'intro' => '同样的板材，设计和安装决定最终好不好用、好不好看。',
                'questions' => [
                    [
                        'key' => 'wardrobe_type', 'text' => '主卧想要衣柜还是衣帽间？', 'type' => 'single',
                        'note' => '衣帽间不一定比衣柜更能装，要扣除通道、转角和开门空间后比较有效收纳量。',
                        'options' => ['靠墙衣柜', '独立 / 步入式衣帽间', '看空间再定'],
                        'option_notes' => ['省空间、最常见', '收纳大、体验好，要有富余面积', '按卧室大小和格局定'],
                    ],
                    [
                        'key' => 'to_ceiling', 'text' => '柜子要不要做到顶？', 'type' => 'single',
                        'note' => '柜体做到顶和门板一门到顶是两件事，都要结合层高、板材稳定性、五金和安装条件。',
                        'options' => ['柜体到顶 + 一门到顶', '柜体到顶 + 分段门板', '顶部留空 / 封板', '看情况'],
                        'option_notes' => ['整体感强，对门板稳定性、铰链和墙顶平整要求高', '同样不积灰，门板尺寸更稳、五金压力较小', '安装容错较高，提前设计封板与清洁方式', '先核对层高、门板限制和实际样板'],
                    ],
                    [
                        'key' => 'cabinet_light', 'text' => '柜内 / 柜下灯光要不要？（可多选）', 'type' => 'multi',
                        'note' => '内置灯光应提前确定电源、开关、驱动散热和检修位置；也可选择后装方案。',
                        'options' => ['要感应灯带（开门即亮）', '要镜前灯 / 化妆灯', '要柜下 / 悬空灯带', '不用'],
                        'option_notes' => ['找东西方便，确认感应方式和驱动检修', '关注显色、眩光和脸部照明，不必锁死单一色温', '提供环境光，避免只顾效果忽略清洁和维修', '可保留插座或走线条件，方便以后加装'],
                    ],
                    [
                        'key' => 'accessibility_safety', 'text' => '家里需要特别照顾哪些使用与安全需求？（可多选）', 'type' => 'multi',
                        'note' => '高柜、悬挂柜和大门板要可靠固定；收纳高度与五金应照顾实际使用者。',
                        'options' => ['儿童防夹 / 防攀爬', '老人易取用', '高柜防倾倒', '轮椅 / 行动不便', '重物承重', '暂时没有'],
                        'option_notes' => ['关注缓冲、防夹、锁具和柜体固定，避免可攀爬抽屉', '常用物放在易够范围，减少弯腰和登高', '确认墙体条件、固定方式和现场验收', '预留通行、膝部空间和适合的操作高度', '保险柜、书籍等提前告知重量和位置', '基础的固定、承重和防夹仍需验收'],
                    ],
                ],
            ],
            [
                'key' => 'budget',
                'title' => '预算与避坑',
                'intro' => '报价要和确认图纸、材料配置及安装范围一起比较，单看每平方米价格意义有限。',
                'questions' => [
                    [
                        'key' => 'budget', 'text' => '全屋定制预算怎么打算？', 'type' => 'single',
                        'note' => '统一柜体范围、内部结构、计价方式、材料和五金后再比总价，并预留合理增项。',
                        'options' => ['优先控制总价', '中等配置、重点位置升级', '更重视设计与服务', '还没概念'],
                        'option_notes' => ['先减少低频柜体和复杂造型，不降低安全与合同透明度', '高频五金、卧室环保和潮湿区材料优先投入', '仍要核对实际材料、系列、图纸和交付标准', '用同一份需求清单让不同方案报价'],
                    ],
                    [
                        'key' => 'drawing_acceptance', 'text' => '签约和安装前，哪些文件准备逐项确认？（可多选）', 'type' => 'multi',
                        'note' => '最终生产应以签字确认的图纸和配置表为准，改单、增项、交期和验收也要书面约定。',
                        'options' => ['平面 / 立面 / 节点图', '材料与五金配置表', '电器和水电接口图', '计价清单与增项规则', '交期 / 保修 / 售后', '安装验收单'],
                        'option_notes' => ['核对尺寸、分格、开门、收口和柜内结构', '写清品牌、型号、颜色、板件部位和数量', '以最终设备型号复核开孔、散热、检修和电源', '明确投影或展开口径、包含项、损耗和改单价格', '明确分批到货、延期责任、保修范围和响应方式', '覆盖外观、尺寸、固定、开合、封边、五金和现场修复'],
                    ],
                    [
                        'key' => 'watch_points', 'text' => '这些全屋定制的坑，哪些你想重点记一记？（可多选）', 'type' => 'multi',
                        'note' => '选出你最想写进合同和安装验收单的项目。',
                        'options' => [
                            '板材环保等级写进合同（防偷换）',
                            '各部位封边工艺和验收效果写清楚',
                            '五金品牌、系列、数量和承重写进合同',
                            '按投影还是展开面积计价，问清楚',
                            '各部位板材厚度、结构和承重写清楚',
                            '见光板同色收口、拉手隐形',
                            '安装缝隙均匀、固定可靠、封边牢固',
                        ],
                        'option_notes' => [
                            '口头说 ENF 没用，等级和型号要落到合同',
                            '柜体、门板可能采用不同工艺，要逐项对应图纸和报价',
                            '同品牌不同系列差异很大，型号、数量和适用门板都要写清',
                            '两种方式都能报价，关键是包含范围一致、能对应图纸复核',
                            '柜体、层板、背板和台面要求不同，按合同和图纸逐项验收',
                            '外露侧板要同色见光板、拉手尽量隐形',
                            '检查缝隙、色差、固定、开合、封边和现场修复',
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
