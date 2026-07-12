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
     * 科普与摸底问卷、署名调研、问答区、公告 / 维权 / 活动 / 互助。
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
        $this->softDecorCensus();
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
     * 问答区有已答/热门未答两条。
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
     * 软装科普征集（装修题库之一，与硬装 / 全屋定制拆开）：纯知识分享、不推销。
     * 面向第一次装修、不知道软装从何下手的业主，行情风格取自 2025 年公开资料。
     * 每题带讲解、每个选项带一句「为什么」：从「软装是什么」到风格色彩、家具、灯光、
     * 窗帘布艺装饰、影音智能、预算避坑，顺着答完就是一条入门路径。
     */
    private function softDecorCensus(): void
    {
        $modules = $this->softDecorModules();

        $census = Matter::factory()->create([
            'type' => 'census',
            'initiator_id' => null,
            'state' => 'open',
            'category' => '软装',
            'title' => '软装怎么搭 · 答题即入门',
            'target_count' => 0,
            'payload' => [
                'purpose' => '硬装做好了，软装却不知从何下手？这份问卷把风格、家具、灯光、窗帘布艺都串成了题：每题有讲解、选项下写清「为什么」，答一遍就能入门。纯科普、不推销。',
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
     * 软装科普问卷题库：讲解写在 note、选项讲解写在 option_notes，答题即建概念。
     * 风格与配色取自 2025 年主流（低饱和治愈色系 + 混搭）。
     *
     * @return array<int, array<string, mixed>>
     */
    private function softDecorModules(): array
    {
        return [
            [
                'key' => 'basics',
                'title' => '先认识软装',
                'intro' => '软装 = 硬装完成后能搬进搬出的一切：家具、窗帘、灯具、床品布艺、地毯、装饰画、绿植摆件…… 它决定家的「气质」和舒适度，好处是能随时换、试错成本低。行话说「轻硬装、重软装」——硬装打好底，软装才是住得舒不舒服、好不好看的关键。',
                'questions' => [
                    [
                        'key' => 'know_level', 'text' => '你对软装了解到哪一步了？', 'type' => 'single', 'required' => true,
                        'note' => '不用有压力，这份问卷每题都带讲解，答一遍就能入门。',
                        'options' => ['完全没概念，第一次了解', '有点想法，但不成体系', '做过一些功课', '已经比较清楚了'],
                        'option_notes' => ['正好，跟着往下答就行', '这份问卷帮你把风格、家具、灯光、布艺串起来', '看看有没有补充的盲区', '当复习，也给拿不准的邻居留个参考'],
                    ],
                    [
                        'key' => 'when_plan', 'text' => '你打算什么时候定软装？', 'type' => 'single',
                        'note' => '很多人硬装做完才想软装，结果墙色、瓷砖、柜色和家具打架。其实软装风格最好在硬装前就定——它反过来决定墙面、瓷砖、柜门颜色和灯光布局。先有整体图，再动工。',
                        'options' => ['硬装前就定好整体风格', '硬装边做边想', '硬装完了再慢慢配', '还没概念'],
                        'option_notes' => ['最理想：风格先行，硬装软装不脱节', '边做边定，注意别和已定的硬装冲突', '能住但容易留遗憾，大件先想好色调', '记住一句：风格越早定越省心'],
                    ],
                ],
            ],
            [
                'key' => 'style',
                'title' => '风格与色彩',
                'intro' => '风格和色彩是软装的骨架。2025 年的主流是「低饱和治愈色系 + 风格混搭」，先定个大方向，别贪多。',
                'questions' => [
                    [
                        'key' => 'style', 'text' => '你更偏好哪种风格？', 'type' => 'single',
                        'note' => '主流风格很多，也越来越爱混搭——先定一个主调再点缀。常见：现代简约、奶油风（奶白温馨）、侘寂（低饱和自然肌理）、新中式 / 宋式（米咖麻色）、法式（优雅）、中古风（拱门复古）、日式原木、北欧（浅色原木）、轻奢（高级灰 + 金属大理石）、美式（沉稳）、现代托斯卡纳 / 自然风（暖大地色 + 微水泥 + 拱形，近年很火）。',
                        'options' => ['现代简约', '奶油风', '侘寂风', '新中式 / 宋式', '法式', '中古风', '日式原木 / MUJI', '北欧', '轻奢', '美式', '现代托斯卡纳 / 自然风', '工业 / 地中海等其他', '还没想好 / 想混搭'],
                        'option_notes' => ['百搭耐看、好落地', '奶白柔软、温馨治愈，当下最热', '低饱和、自然肌理，讲究朴素留白', '米咖麻色、东方雅致，宋式更简约', '优雅浪漫，现在多融现代简约线条', '拱门开放 + 复古家具，层次感强', '原木 + 棉麻，自然清爽好打理', '浅色 + 原木 + 绿植，明亮清爽', '高级灰 + 金属 + 大理石，精致有质感', '沉稳大气，现代美式偏舒适', '暖大地色 + 微水泥 + 拱形，质朴治愈，近年很火', '工业（裸砖金属）、地中海（蓝白）等，个性鲜明', '先找找感觉，混搭是当下常态'],
                    ],
                    [
                        'key' => 'color_tone', 'text' => '整体色调想走哪种？', 'type' => 'single',
                        'note' => '记住配色黄金比例「主色 6 : 辅色 3 : 点缀 1」——墙地顶大面积用低饱和背景色，家具是辅色，抱枕、画、绿植做小面积点缀色提亮。冷暖要统一，别一个空间又冷又暖。',
                        'options' => ['低饱和治愈（奶白 / 燕麦 / 雾蓝，主流）', '明亮清爽（浅色 + 白）', '深色沉稳（高级灰 / 大地色）', '高对比撞色（个性）', '听设计师'],
                        'option_notes' => ['当下最主流、最耐看、最好搭', '通透显大，适合采光一般的房', '沉稳有格调，注意采光和留白', '出效果但难驾驭，点缀用就好', '拿不准就交给专业配色'],
                    ],
                    [
                        'key' => 'color_palette', 'text' => '客餐厅想用哪些主流颜色？（可多选）', 'type' => 'multi',
                        'note' => '客餐厅是家的门面。背景大面积用低饱和主流色，再挑一两个点缀色提亮。下面都是当下耐看的主流色，冷暖尽量统一。',
                        'options' => ['奶白 / 米色', '原木色', '高级灰', '大地色 / 棕咖', '雾霾蓝', '鼠尾草绿 / 橄榄绿', '莫兰迪灰粉', '黑白灰', '暖橘 / 赤陶（托斯卡纳）', '还没定'],
                        'option_notes' => [
                            '最百搭的背景色，奶油风 / 法式常用',
                            '温润治愈，日式 / 北欧 / 自然风的底色',
                            '高级耐脏，轻奢 / 现代简约常用',
                            '沉稳温暖，侘寂 / 托斯卡纳的主色',
                            '清爽安静，适合点缀或卧室墙面',
                            '自然生机，近年很火的点缀色',
                            '柔和高级，法式 / 奶油风点缀',
                            '经典利落，现代简约 / 极简',
                            '暖橘赤陶，托斯卡纳 / 地中海的灵魂色',
                            '看完风格再定配色也不迟',
                        ],
                    ],
                    [
                        'key' => 'material_pref', 'text' => '喜欢哪些材质质感？（可多选）', 'type' => 'multi',
                        'note' => '材质决定质感和高级感。同一空间材质别超过 3~4 种、要互相呼应（比如原木 + 棉麻 + 一点金属）。',
                        'options' => ['原木', '布艺 / 棉麻', '丝绒 / 绒布', '皮革', '金属 / 黄铜', '藤编 / 草编', '石材 / 大理石', '微水泥', '岩板 / 陶瓷', '玻璃 / 亚克力'],
                        'option_notes' => ['温润自然，百搭治愈系', '柔软亲肤，营造松弛感', '轻奢质感、软糯高级，做窗帘沙发点缀', '沉稳有档次，点缀或主沙发', '现代精致，黄铜显轻奢', '自然手作感，适合日式侘寂', '大气高级，电视墙 / 餐桌常用', '无缝质朴，侘寂 / 托斯卡纳的招牌', '大板耐用、颜值高，台面墙面都行', '通透轻盈，显空间'],
                    ],
                ],
            ],
            [
                'key' => 'furniture',
                'title' => '家具',
                'intro' => '家具是软装大件、也是花钱大头。先定沙发、餐桌这几件，尺寸和材质要贴合你家动线和习惯。',
                'questions' => [
                    [
                        'key' => 'sofa', 'text' => '沙发想选哪种材质？', 'type' => 'single',
                        'note' => '布艺（舒适透气、可拆洗，但缝隙难清）、科技布（防污防猫抓、好打理，手感略逊）、真皮（上档次、易擦，但贵、怕划、冬凉）、意式极简 / 模块（灵活组合）。有宠物小孩优先科技布，追求质感选真皮或高档布艺。',
                        'options' => ['布艺（舒适透气）', '科技布（防污防猫抓）', '真皮（上档次易擦）', '模块 / 意式极简', '还没定'],
                        'option_notes' => ['坐感舒服、款式多，选可拆洗款', '有宠物、小孩的省心之选', '有档次、好打理，预算够可上', '能灵活组合，适合大客厅', '按有没有宠物小孩、预算再定'],
                    ],
                    [
                        'key' => 'living_room', 'text' => '客厅打算怎么用？', 'type' => 'single',
                        'note' => '越来越多人客厅「去电视化」——把 C 位让给大桌、投影、书墙或会客区，按家人真实活动来定，别被样板间的「电视 + 茶几 + 沙发」老三样带偏。',
                        'options' => ['传统电视 + 沙发', '投影 + 幕布（去电视化）', '大桌 / 书房化（办公、亲子）', '围坐会客（无电视）', '还没想好'],
                        'option_notes' => ['经典稳妥，注意沙发到电视的距离', '氛围感强，投影和幕布要提前留位', '一家人各干各的，实用又温馨', '爱社交、爱喝茶的选择', '想清楚全家在客厅最常做什么'],
                    ],
                    [
                        'key' => 'dining', 'text' => '餐桌想要什么样的？', 'type' => 'single',
                        'note' => '圆桌省空间、方便交流、人多聚餐好用；长方桌好靠墙、日常实用。就餐人数决定尺寸——六人方桌约 1.3~1.5m。台面岩板 / 大理石好打理有质感，实木温润但要养护。',
                        'options' => ['长方桌（日常实用）', '圆桌（人多、爱聚餐）', '岩板 / 大理石桌', '岛台连餐桌', '还没定'],
                        'option_notes' => ['靠墙省地，最常见', '交流方便、显气氛，占地稍大', '好打理、颜值高', '开放厨房 + 餐厨一体，动线高效', '按就餐人数和厨房布局定'],
                    ],
                    [
                        'key' => 'furniture_material', 'text' => '成品家具 / 柜子材质倾向？', 'type' => 'single',
                        'note' => '实木（原木，环保、质感、耐用，但贵、干燥易裂）；板式（款式多、性价比高，认准环保等级）；很多人大件实木、小件板式混搭。',
                        'options' => ['实木 / 原木（质感环保）', '板式（实惠款多）', '实木 + 板式混搭', '不懂 / 看预算'],
                        'option_notes' => ['耐用有质感，注意保养、避开地暖暴晒', '性价比高，认准 ENF / E0 环保等级', '按重要程度和预算分配', '按预算和风格让设计师配'],
                    ],
                ],
            ],
            [
                'key' => 'lighting',
                'title' => '灯光',
                'intro' => '灯光是软装的「隐形化妆师」——同一个家，灯光对了氛围立马不同。注意：无主灯、氛围灯的布线要在硬装阶段就留好。',
                'questions' => [
                    [
                        'key' => 'lighting_approach', 'text' => '灯光想怎么做？', 'type' => 'single',
                        'note' => '无主灯（用筒灯、射灯、灯带做分层照明，无顶灯、更高级通透，但要硬装阶段布点布线、造价高、对吊顶找平要求高）；传统主灯（一室一顶灯，简单实惠）；主灯 + 氛围灯（最实用的折中）。',
                        'options' => ['无主灯（分层照明、高级）', '传统主灯（简单实惠）', '主灯 + 氛围灯（折中）', '还没定'],
                        'option_notes' => ['通透高级，但要提前布线、预算够再上', '省钱省事、够用', '既有主照明又有氛围，最实用', '看层高、预算和吊顶方案定'],
                    ],
                    [
                        'key' => 'color_temp', 'text' => '灯光色温偏好？', 'type' => 'single',
                        'note' => '色温决定冷暖：暖光 3000K 温馨放松（客厅、卧室），中性光 4000K 自然（餐厅、过道），冷白 5000K+ 明亮（厨房、卫生间、书房看得清）。全屋色温尽量统一或分区一致；认准显色指数 CRI>90，颜色才不失真。',
                        'options' => ['全屋暖光（3000K，温馨）', '全屋中性（4000K，自然）', '分区：公共区中性、卧室暖光', '还没概念'],
                        'option_notes' => ['适合放松，注意书房够不够亮', '明亮自然、百搭', '最讲究的做法，按空间功能配', '记住：暖光放松、冷光提神，CRI>90'],
                    ],
                    [
                        'key' => 'ambiance', 'text' => '想要哪些氛围灯光？（可多选）', 'type' => 'multi',
                        'note' => '氛围灯让家更有层次，大多要提前留电位。',
                        'options' => ['灯带（吊顶 / 柜下）', '落地灯 / 台灯', '射灯洗墙（打亮装饰）', '智能调光调色', '简单够用就行'],
                        'option_notes' => ['勾勒线条、氛围感强', '局部照明 + 装饰，随时挪', '突出挂画、材质墙', '一键切换场景，需智能面板', '基础照明够用也没问题'],
                    ],
                ],
            ],
            [
                'key' => 'textile',
                'title' => '窗帘 · 布艺 · 装饰',
                'intro' => '窗帘、床品、抱枕、挂画这些「软」的东西，是换季、换心情、换风格最快最省的手段。',
                'questions' => [
                    [
                        'key' => 'curtain', 'text' => '窗帘有什么要求？', 'type' => 'single',
                        'note' => '卧室要「高遮光」（看遮光率，睡得好）；客厅可用纱帘透光或遮光 + 纱双层。窗帘褶皱倍数做到 2 倍才饱满好看；想要电动窗帘，要在硬装水电阶段留好电位。',
                        'options' => ['卧室高遮光', '客厅纱帘透光', '遮光 + 纱双层', '电动窗帘', '还没定'],
                        'option_notes' => ['遮光率高才睡得好', '柔化光线、通透', '兼顾遮光和透光，最实用', '方便但要提前留电位', '按房间朝向和用途定'],
                    ],
                    [
                        'key' => 'soft_textile', 'text' => '布艺、床品这块在意吗？（可多选）', 'type' => 'multi',
                        'note' => '布艺是最容易换、最能改变体感和风格的软装。床品看材质（棉透气、天丝顺滑凉感、水洗棉柔软）；地毯提升松弛感但要考虑清洁；抱枕靠垫是点缀色的好载体。',
                        'options' => ['床品四件套材质', '地毯', '抱枕 / 靠垫', '桌布 / 餐垫', '不太讲究'],
                        'option_notes' => ['贴身用，选透气亲肤的材质', '松弛感神器，注意好不好清洁', '最省钱的点缀色和层次感', '餐桌氛围小物', '基础配齐即可'],
                    ],
                    [
                        'key' => 'decor', 'text' => '装饰想要哪些？（可多选）', 'type' => 'multi',
                        'note' => '装饰讲究「少而精」，留白也是设计，堆太满反而显乱。挂画中心高度约 1.5m（视线平齐）；绿植增添生气。',
                        'options' => ['装饰画 / 挂画', '摆件 / 手办', '绿植', '香薰 / 蜡烛', '艺术摆台 / 托盘', '留白极简'],
                        'option_notes' => ['提气质，注意挂画高度和比例', '展示个性，别铺太满', '增加生机、软化空间', '氛围和气味，治愈系', '把小物归拢得更精致', '少即是多，留白很高级'],
                    ],
                ],
            ],
            [
                'key' => 'extras',
                'title' => '影音智能 · 预算 · 避坑',
                'intro' => '最后收个尾：影音智能这些设备要趁硬装预留，预算上软装可以慢慢来。',
                'questions' => [
                    [
                        'key' => 'av_smart', 'text' => '影音 / 智能这些要不要？（可多选）', 'type' => 'multi',
                        'note' => '这些设备的布线、供电、上下水都要在硬装阶段预留，软装阶段主要是选品牌型号。别等住进去才想起来。',
                        'options' => ['家庭影院 / 音响', '智能家居联动', '扫地机器人基站（留上下水）', '安防 / 监控', '暂不考虑'],
                        'option_notes' => ['音响走线、功放位要提前留', '灯光窗帘家电联动，需智能面板和网络', '自动集尘 / 上下水，要预留水电', '摄像头、门磁点位提前规划', '够用也行，但布线现在不做以后难补'],
                    ],
                    [
                        'key' => 'budget', 'text' => '软装预算怎么打算？', 'type' => 'single',
                        'note' => '软装可以「先搬进去、慢慢添」，不必一次配齐。建议大件（沙发、床、餐桌、主灯）先定好、定调子，小件（装饰、布艺、绿植）慢慢淘、边住边补，反而更有生活气。',
                        'options' => ['越省越好，慢慢添', '中等，先配齐主要家具', '一步到位，成套软装', '还没概念'],
                        'option_notes' => ['大件先买、小件慢淘，最省也最有生活味', '主要家具先到位，装饰后补', '省心但一次投入大，注意别买成样板间', '看完区间和风格再估'],
                    ],
                    [
                        'key' => 'watch_points', 'text' => '这些软装的坑，哪些想记一记？（可多选）', 'type' => 'multi',
                        'note' => '软装最容易「买了后悔」，记住这几条少踩坑。',
                        'options' => [
                            '风格先定再买、别东拼西凑',
                            '大件先量尺寸、留够过道动线',
                            '布艺 / 床垫也有甲醛，看环保',
                            '网红图慎入、买家秀≠卖家秀',
                            '别被样板间带偏、按需选',
                            '留白别堆满',
                        ],
                        'option_notes' => [
                            '没有主线的软装最容易乱、显廉价',
                            '沙发餐桌买大了堵动线，先量好尺寸',
                            '床垫、窗帘、地毯都可能释放甲醛，认环保',
                            '实物质感、颜色常和图差很远',
                            '岛台、去电视化好看但不一定适合你',
                            '克制地留白，比堆满更高级',
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
