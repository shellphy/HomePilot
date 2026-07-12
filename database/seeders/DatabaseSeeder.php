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
                'intro' => '全屋定制 = 把家里的柜子（衣柜、橱柜、鞋柜、餐边柜、电视柜、榻榻米…）交给工厂，按你家尺寸设计、生产、上门安装。它和「木工现场打柜」是两条路。一套柜子好不好，就看三件事：板材（决定环保）、设计（决定好不好用）、安装（决定落不落地）。品牌参考意义不大——板材不是他们自己产的，他们主要做设计、裁切、封边和安装。',
                'questions' => [
                    [
                        'key' => 'know_level', 'text' => '你对全屋定制了解到哪一步了？', 'type' => 'single', 'required' => true,
                        'note' => '不用有压力，这份问卷每题都带讲解，答一遍就能入门。',
                        'options' => ['完全没概念，第一次了解', '听过一些名词，但不懂门道', '做过一些功课', '已经比较清楚了'],
                        'option_notes' => ['正好，跟着往下答就行', '这份问卷帮你把板材、封边、五金串起来', '看看有没有补充的盲区', '当复习，也给拿不准的邻居留个参考'],
                    ],
                    [
                        'key' => 'vs_carpenter', 'text' => '柜子想走全屋定制，还是木工现场打？', 'type' => 'single',
                        'note' => '全屋定制：工厂机器封边更规整、有专业设计、售后完善，但柜体常留「收口条」难做到严丝合缝。木工现场打：能一门到顶、和墙顶严丝合缝、容错高，但很吃板材质量和木工手艺（现在也多是机器封边）。能解决板材、手艺、图纸，木工现场更灵活；否则全屋定制更省心。',
                        'options' => ['全屋定制（工厂，省心）', '木工现场打（严丝合缝）', '都看看 / 还不懂'],
                        'option_notes' => ['设计 + 售后省心，注意收口和封边工艺', '严丝合缝、一门到顶，关键看板材和师傅手艺', '看完讲解、对比报价再定'],
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
                            '潮湿环境，优先多层实木板',
                        ],
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
                        'note' => '主流选择：颗粒板（实木颗粒板 / 刨花板，造价低、握钉强、抗变形，最主流，但不耐潮）；实木多层板（防水耐潮、承重强，适合橱柜、浴室柜，贵一些）；欧松板 / 禾香板（更环保、强度高，价更高、花色少）。厨卫等潮湿处优先多层实木。',
                        'options' => ['颗粒板（主流、性价比）', '多层实木（防潮、厨卫浴室柜）', '欧松板 / 禾香板（更环保）', '不懂，听设计师'],
                        'option_notes' => ['家用最多的选择，认准环保等级和封边', '潮湿环境更稳，橱柜浴室柜首选', '环保和强度更高、花色偏少、价更高', '让设计师按空间潮湿度和预算配'],
                    ],
                    [
                        'key' => 'eco_grade', 'text' => '板材环保等级卡到哪级？', 'type' => 'single',
                        'note' => '国标分 E1（≤0.124）、E0（≤0.050）、ENF（≤0.025、无醛添加）三级，认准 GB/T 39600；ENF 最高、E1 不推荐室内。日本 F4 星、欧标 NAF 与 ENF 相当。柜子面积大、封在卧室，等级越高越安心。',
                        'options' => ['ENF 级（无醛添加、最高）', 'E0 级', 'E1 也行 / 看预算', '不懂'],
                        'option_notes' => ['有小孩老人首选，认准检测报告型号一致', '够用的高等级，比 E1 强不少', 'E1 是底线，尽量往上够一级', '记住一句：至少 E0、优先 ENF'],
                    ],
                    [
                        'key' => 'door_finish', 'text' => '柜门（门板）想要什么感觉？', 'type' => 'single',
                        'note' => '门板看饰面工艺：PET / PETG（纯色、色彩鲜、食品级环保、耐磨，PETG 比 PET 更硬）；烤漆（色彩饱满有光泽，但怕磕、贵）；实木 / 包覆（实木质感、适合中式美式，贵）；双饰面（基材颗粒板、耐磨实惠，但怕水、不宜卫生间）。密度板、木工板做门板不推荐（环保 / 易变形）。',
                        'options' => ['PET / PETG（纯色、耐磨、环保）', '烤漆（色彩饱满、有光泽）', '实木 / 包覆（实木质感）', '双饰面（经济耐磨）', '不懂 / 看效果'],
                        'option_notes' => ['现代风常用，选 PETG 更耐刮', '颜值高但怕磕碰、价更高', '质感最好、偏贵，适合中式美式', '性价比，注意别用在卫生间', '按风格和预算让设计师推荐'],
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
                        'note' => '封边把板材切口封住、防潮也防甲醛外溢。EVA（最便宜、胶量大、易开裂、胶线明显，不推荐）；PUR（耐温防潮、胶线不明显，主流升级）；激光封边（无胶线、最牢最美，最贵）。常见搭配「PUR 柜体 + 激光门板」。注意：不少商家用 PUR 冒充激光，也可能门板 PUR、柜体偷偷用 EVA——把封边工艺写进合同。',
                        'options' => ['要好的（激光 / PUR）', '普通 EVA 够用', '不懂'],
                        'option_notes' => ['耐用又美观，认准工艺、别被 PUR 冒充激光', '预算有限可用，但白色板胶线明显、厨卫易脱', '记住：优先 PUR / 激光，写进合同'],
                    ],
                    [
                        'key' => 'hardware', 'text' => '柜子五金在不在意？', 'type' => 'single',
                        'note' => '板材决定环保，五金决定耐用——后期出问题多在铰链、滑轨这些五金上。认准大牌：奥地利百隆、德国海蒂斯，国产东泰（DTC）也不错。要求五金带统一 LOGO、写进合同（百隆、海蒂斯假货多）。',
                        'options' => ['要大牌（百隆 / 海蒂斯 / 东泰）', '附送的够用就行', '不懂'],
                        'option_notes' => ['开合几万次的东西，值得上好的', '至少认准正规品牌、别用杂牌', '记住：五金决定寿命，写进合同'],
                    ],
                ],
            ],
            [
                'key' => 'kitchen_cabinet',
                'title' => '橱柜（台面 · 水槽）',
                'intro' => '橱柜是全屋定制里最复杂的一块——柜体、台面、水槽、五金、嵌入电器全挤在厨房。台面和水槽尤其影响日后好不好用（柜体板材见前面「板材」那题，厨房潮湿、优先多层实木）。',
                'questions' => [
                    [
                        'key' => 'countertop', 'text' => '橱柜台面想用哪种？', 'type' => 'single',
                        'note' => '主流二选一：石英石（耐磨耐高温、性价比高、花色多，缺点是有拼接缝，认准石英含量高、大牌防渗透）和岩板（颜值高、可一体无缝、耐高温耐刮，但边角脆、怕磕、贵、加工要求高）。其它：不锈钢（耐用抗菌、易留划痕水渍）、天然大理石（美但怕油污渗透、要养护）。',
                        'options' => ['石英石（耐用、性价比）', '岩板（颜值高、可无缝）', '不锈钢（耐用抗菌）', '还没定 / 听设计师'],
                        'option_notes' => ['最主流稳妥，认准石英含量和大牌', '好看好打理，注意边角防磕、预算更高', '实用工业风，介意划痕的慎选', '按预算和风格再定'],
                    ],
                    [
                        'key' => 'sink', 'text' => '水槽你想要哪些？（可多选）', 'type' => 'multi',
                        'note' => '两个关键：① 安装方式——「台下盆」（盆沿在台面下，台面水能直接刮进盆、好清理）优于「台上盆」（便宜但边缘藏污）；② 大小——大单槽能放下炒锅、烤盘，比双槽实用。材质主流 304 不锈钢（可选纳米涂层防刮），也有石英石水槽。想装垃圾处理器要提前留电位和柜内空间。',
                        'options' => ['台下盆（好清理）', '大单槽（能放大锅）', '304 不锈钢 / 纳米涂层', '预留垃圾处理器', '还没想好'],
                        'option_notes' => ['台面水直接刮进盆，强烈推荐', '比双槽实用，洗大件方便', '主流耐用，纳米涂层更防刮', '要提前留电位和柜内空间', '看使用习惯再定'],
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
                        'note' => '长衣（大衣、连衣裙、风衣）要「长衣区」（挂杆下留约 1.4m）；短衣（衬衫、T 恤、外套）可上下双挂杆、更省空间。按你衣物的比例定长短区，别一刀切。',
                        'options' => ['长衣多', '短衣、叠放多', '差不多、都有'],
                        'option_notes' => ['长衣区为主，留足垂挂高度', '多做双挂杆和叠放区，容量翻倍', '长短区搭配，最常见'],
                    ],
                    [
                        'key' => 'hang_vs_fold', 'text' => '你更习惯挂衣服还是叠衣服？', 'type' => 'single',
                        'note' => '能挂则挂——好找、不压皱、拿取方便，但占空间；叠放更省地方但容易翻乱。多数人挂放为主、叠放为辅（内衣袜子用抽屉分隔件）。',
                        'options' => ['能挂就挂（好找不皱）', '习惯叠放（省空间）', '挂叠都要'],
                        'option_notes' => ['多留挂衣区和挂杆', '多做层板和抽屉，配分隔件', '按比例分区，挂放为主叠放为辅'],
                    ],
                    [
                        'key' => 'shoes_count', 'text' => '鞋子大概多少双？', 'type' => 'single',
                        'note' => '鞋柜按数量和高度规划：多做「活动层板」（能上下调）应对靴子、鞋盒；进门处留常穿鞋的开放格 + 换鞋凳更方便。',
                        'options' => ['不多（20 双以内）', '中等（20~50 双）', '很多（50 双以上）'],
                        'option_notes' => ['基础鞋柜够用', '留活动层板，分季节收纳', '考虑玄关整墙柜或独立鞋帽间'],
                    ],
                    [
                        'key' => 'storage_extra', 'text' => '还有哪些要专门收纳的？（可多选）', 'type' => 'multi',
                        'note' => '这些各有讲究，提前说了设计师才好留位。',
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
                        'note' => '衣柜靠墙、省空间；独立 / 步入式衣帽间收纳大、更有仪式感，但占面积。衣帽间三种布局：U 型（最能装，最小约 1.5×2m）、L 型（利用转角，约 1.5×1.8m）、平行（长条空间，约 2×2.1m）。',
                        'options' => ['靠墙衣柜', '独立 / 步入式衣帽间', '看空间再定'],
                        'option_notes' => ['省空间、最常见', '收纳大、体验好，要有富余面积', '按卧室大小和格局定'],
                    ],
                    [
                        'key' => 'to_ceiling', 'text' => '柜子要不要做到顶？', 'type' => 'single',
                        'note' => '做到顶（一门到顶）：不积灰、储物多、显层高，但要求墙面、顶面平整（顶不平会有缝，只能用收口条挡）。不到顶：上方易积灰、要留收口。木工现场更容易做到严丝合缝、一门到顶。',
                        'options' => ['一门到顶（不积灰、能装）', '留顶收口（省点、好装）', '看情况'],
                        'option_notes' => ['颜值和收纳都好，前提是墙顶够平', '安装容错高，但顶上要常擦灰', '看层高和墙面平整度'],
                    ],
                    [
                        'key' => 'cabinet_light', 'text' => '柜内 / 柜下灯光要不要？（可多选）', 'type' => 'multi',
                        'note' => '柜内感应灯带（开门即亮、找衣服方便）、镜前灯（化妆区、色温约 4300K 显色好）、柜下 / 悬空灯带（氛围感）。这些都要在硬装水电阶段预留电位，事后难加。',
                        'options' => ['要感应灯带（开门即亮）', '要镜前灯 / 化妆灯', '要柜下 / 悬空灯带', '不用'],
                        'option_notes' => ['找东西方便、体验好，记得留电位', '化妆区实用，选 4300K 显色好', '氛围感强，悬空柜标配', '简单省事、够用就行'],
                    ],
                ],
            ],
            [
                'key' => 'budget',
                'title' => '预算与避坑',
                'intro' => '全屋定制水很深，价格从每张板两百到上千不等，看板材、封边、五金和设计。',
                'questions' => [
                    [
                        'key' => 'budget', 'text' => '全屋定制预算怎么打算？', 'type' => 'single',
                        'note' => '品牌溢价高——你找的二三线品牌，八成是当地加工厂代工。想省钱可以直接找靠谱的当地工厂（看封边机、板材品牌、五金），自己盯板材 / 封边 / 五金，能省三成以上。计价常按「投影面积」或「展开面积」，一定要问清是哪种（展开面积把内部层板抽屉都算上，更贵也更透明）。',
                        'options' => ['越省越好（找当地工厂）', '中等，盯板材五金', '一步到位（大牌 / 高配）', '还没概念'],
                        'option_notes' => ['源头工厂 + 自己盯细节最省', '把钱花在板材环保和五金耐用上', '大牌省心，但溢价高、也要核封边五金', '看完区间和需求再估'],
                    ],
                    [
                        'key' => 'watch_points', 'text' => '这些全屋定制的坑，哪些你想重点记一记？（可多选）', 'type' => 'multi',
                        'note' => '看懂这几条，就不容易被套路。',
                        'options' => [
                            '板材环保等级写进合同（防偷换）',
                            '封边工艺写清（防 PUR 冒充激光、柜体偷用 EVA）',
                            '五金品牌 + 统一 LOGO 写进合同',
                            '按投影还是展开面积计价，问清楚',
                            '板材厚度（柜体 18 / 背板 9 / 台面 25mm）',
                            '见光板同色收口、拉手隐形',
                            '安装严丝合缝、无大缝隙、封边不脱',
                        ],
                        'option_notes' => [
                            '口头说 ENF 没用，等级和型号要落到合同',
                            '门板 PUR、柜体 EVA 是常见套路，全写清',
                            '五金决定寿命，品牌落到合同',
                            '展开面积更贵但更透明，别被投影面积模糊报价',
                            '缩板材厚度最难发现，验收拿尺量',
                            '外露侧板要同色见光板、拉手尽量隐形',
                            '缝隙、色差、脱胶是安装验收的重点',
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
