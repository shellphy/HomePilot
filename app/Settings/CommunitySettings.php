<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * 社区设置：身份文案与结构选项，全部存数据库、小程序「小区管理 · 社区设置」页可视化编辑。
 * 出厂默认值在 database/settings 迁移里。
 */
class CommunitySettings extends Settings
{
    public string $name;

    public string $slogan;

    public string $sub_slogan;

    public string $initiator_note;

    public string $data_footnote;

    /** @var array<int, string> */
    public array $buildings;

    public static function group(): string
    {
        return 'community';
    }
}
