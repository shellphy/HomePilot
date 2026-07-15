<?php

namespace App\Console\Commands;

use App\Models\Resident;
use Illuminate\Console\Command;

class GrantAdmin extends Command
{
    protected $signature = 'admin:grant {resident : 成员 ID 或手机号（昵称会重复，不支持）} {--super : 设为超级管理员（可在小程序内增减管理员）} {--revoke : 收回管理员权限}';

    protected $description = '把某个成员设为管理员（或收回）；--super 种下超级管理员，之后增减管理员在小程序内完成';

    public function handle(): int
    {
        $key = $this->argument('resident');

        $resident = Resident::find($key)
            ?? Resident::where('phone', $key)->where('phone', '!=', '')->first();

        if (! $resident) {
            $this->error("找不到成员：{$key}（可用 ID 或手机号，手机号在小程序「个人资料」里授权）");

            return self::FAILURE;
        }

        $revoke = $this->option('revoke');
        $resident->forceFill([
            'is_admin' => ! $revoke,
            // 收回时一并撤掉超管；授权时仅 --super 升为超管，普通授权不动超管身份
            'is_super_admin' => $revoke ? false : ($this->option('super') ? true : $resident->is_super_admin),
        ])->save();

        $this->info(sprintf(
            '%s（ID %d）%s%s权限',
            $resident->nickname ?: $resident->unionid,
            $resident->id,
            $revoke ? '已收回' : '已获得',
            $resident->is_super_admin ? '超级管理员' : '管理员',
        ));

        return self::SUCCESS;
    }
}
