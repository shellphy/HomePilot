<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * 去掉独立的「小程序名称」：导航栏与分享标题直接用小区名称（community.name），
     * 微信分享卡片本身会带小程序认证名称，无需在标题里重复。
     */
    public function up(): void
    {
        $this->migrator->delete('community.app_name');
    }
};
