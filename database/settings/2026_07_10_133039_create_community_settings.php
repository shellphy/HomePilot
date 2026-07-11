<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * 社区设置的出厂默认值（仅首次迁移写入）；之后一切修改都在小程序「小区管理 · 社区设置」页完成。
     */
    public function up(): void
    {
        $this->migrator->add('community.name', '天青府');
        $this->migrator->add('community.app_name', '天青府家园');
        $this->migrator->add('community.slogan', '咱们小区自己的公共空间');
        $this->migrator->add('community.sub_slogan', '公益运营 · 不代收任何款项');
        $this->migrator->add('community.pledge', '本小程序公益运营 · 商家返点全部转为参团业主让利，随成交公示 · 平台不代收任何款项');
        $this->migrator->add('community.initiator_note', '发起即成为本团团长，负责对接商家和更新进度。小程序不代收任何款项，签约与付款由业主直接对商家；商家给到的任何返点，须全部转为参团业主让利，并在成团后随成交公示摊开。');
        $this->migrator->add('community.initiate_hint', '团购、活动、拼车互助、维权联名都可以 · 你来牵头，管理员审核后对全小区公示');
        $this->migrator->add('community.data_footnote', '收房、车位等主题的征集开始后，数据会出现在这里');
        $this->migrator->add('community.total_households', 600);
        $this->migrator->add('community.layouts', ['107㎡', '130㎡', '154㎡']);
        $this->migrator->add('community.decoration_modes', ['全包（都交给装修公司）', '半包（主材自己买）', '清包（只请工人）', '还没定']);
        $this->migrator->add('community.categories', ['装修公司', '中央空调', '地暖', '全屋定制', '门窗', '软装家具', '瓷砖']);
    }
};
