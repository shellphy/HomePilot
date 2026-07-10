<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * 社区设置：身份文案与结构选项，全部存数据库、Filament「社区设置」页可视化编辑。
 * 出厂默认值在 database/settings 迁移里。
 */
class CommunitySettings extends Settings
{
    public string $name;

    public string $app_name;

    public string $slogan;

    public string $sub_slogan;

    public string $pledge;

    public string $initiator_note;

    public string $initiate_hint;

    public string $data_footnote;

    public int $total_households;

    /** @var array<int, string> */
    public array $layouts;

    /** @var array<int, string> */
    public array $decoration_modes;

    /** @var array<int, string> */
    public array $categories;

    public static function group(): string
    {
        return 'community';
    }
}
