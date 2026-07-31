<?php

namespace App\Services;

use App\Models\Resident;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class VerifyOwner
{
    public function handle(Resident $resident, string $code): void
    {
        if ($resident->affiliated_party_id !== null) {
            Log::warning('相关方尝试业主邀请码认证', [
                'resident_id' => $resident->id,
                'party_id' => $resident->affiliated_party_id,
            ]);

            throw ValidationException::withMessages([
                'invite_code' => '请先把身份切换为业主，再填写邀请码',
            ]);
        }

        $admin = DB::transaction(function () use ($resident, $code): ?Resident {
            $lockedResident = Resident::query()->lockForUpdate()->findOrFail($resident->id);

            if ($lockedResident->owner_verified_at !== null) {
                return null;
            }

            $invitingAdmin = Resident::query()
                ->where('is_admin', true)
                ->where('owner_invitation_code', $code)
                ->where('owner_invitation_code_expires_at', '>', now())
                ->first();

            if ($invitingAdmin === null) {
                Log::warning('无效业主邀请码', [
                    'resident_id' => $resident->id,
                ]);

                throw ValidationException::withMessages([
                    'invite_code' => '邀请码无效或已过期，请向管理员获取有效邀请码',
                ]);
            }

            $lockedResident->forceFill([
                'owner_verified_at' => now(),
                'owner_verified_by_id' => $invitingAdmin->id,
            ])->save();

            return $invitingAdmin;
        });

        if ($admin !== null) {
            Log::info('审计 · 邀请码认证业主', [
                'resident_id' => $resident->id,
                'admin_id' => $admin->id,
            ]);
        }
    }
}
