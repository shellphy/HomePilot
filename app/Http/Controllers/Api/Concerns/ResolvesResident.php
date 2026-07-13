<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Resident;
use Illuminate\Http\Request;

trait ResolvesResident
{
    /**
     * 当前登录的业主（API 守卫签发给 Resident）。
     */
    protected function resident(Request $request): Resident
    {
        $resident = $request->user('sanctum');

        abort_unless($resident instanceof Resident, 401);

        return $resident;
    }

    /**
     * 参与类动作（提问/回复/接龙/发起/答问卷）前置：被拉黑的成员仍可浏览，但不能写入。
     */
    protected function assertNotBlocked(Resident $resident): void
    {
        abort_if($resident->isBlocked(), 403, '你已被管理员限制参与社区互动');
    }
}
