<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * 移除无消费方的设置项：pledge/initiate_hint 从未被小程序展示，
     * total_households 仅曾随 /stats 下发但无人使用，
     * layouts/decoration_modes 只在建库种子里用作首份问卷的选项（问卷创建后即快照，改设置不生效）。
     */
    public function up(): void
    {
        $this->migrator->delete('community.pledge');
        $this->migrator->delete('community.initiate_hint');
        $this->migrator->delete('community.total_households');
        $this->migrator->delete('community.layouts');
        $this->migrator->delete('community.decoration_modes');
    }
};
