<?php

namespace App\Services;

use App\Models\Resident;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GetCurrentOwnerInvitationCode
{
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private const CODE_LENGTH = 8;

    /**
     * @return array{code: string, expires_at: CarbonInterface}
     */
    public function handle(Resident $admin): array
    {
        return DB::transaction(function () use ($admin): array {
            $lockedAdmin = Resident::query()->lockForUpdate()->findOrFail($admin->id);

            if ($lockedAdmin->owner_invitation_code !== null
                && $lockedAdmin->owner_invitation_code_expires_at?->isFuture()) {
                return [
                    'code' => $lockedAdmin->owner_invitation_code,
                    'expires_at' => $lockedAdmin->owner_invitation_code_expires_at,
                ];
            }

            $code = $this->uniqueCode();
            $expiresAt = now()->addDay()->startOfDay();

            $lockedAdmin->forceFill([
                'owner_invitation_code' => $code,
                'owner_invitation_code_expires_at' => $expiresAt,
            ])->save();

            Log::info('审计 · 生成业主邀请码', [
                'admin_id' => $lockedAdmin->id,
                'expires_at' => $expiresAt->toIso8601String(),
            ]);

            return ['code' => $code, 'expires_at' => $expiresAt];
        });
    }

    private function uniqueCode(): string
    {
        do {
            $code = collect(range(1, self::CODE_LENGTH))
                ->map(fn (): string => self::CODE_ALPHABET[random_int(0, Str::length(self::CODE_ALPHABET) - 1)])
                ->implode('');
            $exists = Resident::query()
                ->where('owner_invitation_code', $code)
                ->exists();
        } while ($exists);

        return $code;
    }
}
