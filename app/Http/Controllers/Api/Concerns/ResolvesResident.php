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
}
