<?php

namespace Database\Seeders;

use App\Models\ProgressUpdate;
use App\Models\Project;
use App\Models\Registration;
use App\Models\Resident;
use App\Models\Signup;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * 本地联调数据：团长账号 + 三个示例团购 + 一批登记。
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => '管理员',
            'email' => 'admin@homepilot.test', // 密码：password
        ]);

        $initiator = Resident::factory()->create([
            'nickname' => '老K',
            'unit_label' => '3-2-1801',
        ]);

        $decoration = Project::factory()->open()->for($initiator, 'initiator')->create([
            'category' => '装修公司',
            'title' => '「城建装饰」整装团购',
            'target_households' => 20,
            'pitch' => '我自己家就签的这家，合同、报价单、工地进度全部公开，大家随时来我家工地看。谈下来的条件对每一户一样。',
            'terms' => [
                ['label' => '半包', 'value' => '618 元/㎡（门市 688）'],
                ['label' => '全包', 'value' => '1150 元/㎡（门市 1280）'],
                ['label' => '质保', 'value' => '隐蔽工程 10 年，写入合同'],
            ],
            'perk' => '满 20 户赠全屋水电升级（点位不限量）',
            'glossary' => [
                ['term' => '半包 vs 全包', 'explain' => '半包=人工+辅料，主材自己买；全包=全含。差价大头在主材品牌，对比报价先看主材清单。'],
                ['term' => '水电点位', 'explain' => '每个插座、开关、出水口算一个点位，按个收费最容易超预算——所以"点位不限量"是实在让利。'],
                ['term' => '隐蔽工程质保', 'explain' => '封进墙里的水电和防水出问题维修最贵，质保年限必须写进合同。'],
            ],
        ]);

        Signup::factory()->count(17)->for($decoration)->create();

        ProgressUpdate::factory()->for($decoration)->create([
            'happened_on' => now()->subDays(7)->toDateString(),
            'content' => '团长家水电开槽完成，横平竖直验收通过',
        ]);
        ProgressUpdate::factory()->for($decoration)->create([
            'happened_on' => now()->subDays(19)->toDateString(),
            'content' => '团长家开工交底，报价单（脱敏）已公开',
        ]);

        $hvac = Project::factory()->negotiating()->for($initiator, 'initiator')->create([
            'category' => '中央空调',
            'title' => '中央空调方案征集',
            'target_households' => 25,
            'perk' => '满 25 户总价再降 3%',
            'terms' => [['label' => '方案', 'value' => '大金 / 美的两套方案对比中']],
        ]);
        Signup::factory()->count(23)->for($hvac)->create();

        Project::factory()->for($initiator, 'initiator')->create([
            'category' => '全屋定制',
            'title' => '全屋定制柜体 意向征集',
            'target_households' => 15,
            'terms' => [],
            'glossary' => [],
        ]);

        Registration::factory()->count(46)->create();
    }
}
