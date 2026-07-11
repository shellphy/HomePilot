<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * 社区设置的出厂默认值（仅首次迁移写入）；之后一切修改都在小程序「小区管理 · 社区设置」页完成。
     * 每个设置项都必须有真实消费方（展示或校验），没有消费方的不进设置。
     */
    public function up(): void
    {
        $this->migrator->add('community.name', '武汉招商天青府');
        $this->migrator->add('community.slogan', '咱们小区自己的公共空间');
        $this->migrator->add('community.sub_slogan', '业主公益运营');
        $this->migrator->add('community.initiator_note', '发起即成为本团团长，负责对接商家和更新进度。签约与付款由业主直接对商家，商家给到的返点全部转为参团业主让利，随成交公示摊开。');
        $this->migrator->add('community.admin_contact', '业主群里@管理员，或到物业前台找管理员');
        // 小区硬条件（AI 答疑的背景知识）：外机位、层高这类只有本小区才知道的约束，
        // 消费方是 MatterExplainer（业主侧 AI 答疑）的上下文注入
        $this->migrator->add('community.ai_context', '每户只有一个空调外机位，基本只能装中央空调（一拖多）；层高约 2.95 米，吊顶后约 2.6 米。');
        $this->migrator->add('community.buildings', ['1栋', '2栋', '3栋', '4栋', '5栋', '6栋', '7栋', '8栋']);
    }
};
