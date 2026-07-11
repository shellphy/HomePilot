<?php

namespace App\Console\Commands;

use App\Models\Resident;
use Illuminate\Console\Command;

class GrantAdmin extends Command
{
    protected $signature = 'admin:grant {resident : 成员 ID 或微信号（昵称会重复，不支持）} {--revoke : 收回管理员权限}';

    protected $description = '把某个成员设为管理员（或收回），管理操作全部在小程序「我的」里完成';

    public function handle(): int
    {
        $key = $this->argument('resident');

        $resident = Resident::find($key)
            ?? Resident::where('wechat_id', $key)->where('wechat_id', '!=', '')->first();

        if (! $resident) {
            $this->error("找不到成员：{$key}（可用 ID 或微信号，微信号在小程序「个人资料」里填写）");

            return self::FAILURE;
        }

        $resident->forceFill(['is_admin' => ! $this->option('revoke')])->save();

        $this->info(sprintf(
            '%s（ID %d）%s管理员权限',
            $resident->nickname ?: $resident->openid,
            $resident->id,
            $this->option('revoke') ? '已收回' : '已获得',
        ));

        return self::SUCCESS;
    }
}
