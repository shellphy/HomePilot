<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * 移除团购品类预设：发起团购时品类改为自由填写，
     * 相应的品类意向统计（/stats 的 category_interest）一并下线。
     */
    public function up(): void
    {
        $this->migrator->delete('community.categories');
    }
};
