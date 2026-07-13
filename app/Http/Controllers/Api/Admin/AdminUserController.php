<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Concerns\ResolvesResident;
use App\Http\Controllers\Controller;
use App\Models\Resident;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * 超级管理端 · 管理员：查看、按授权手机号增补、收回。只有超级管理员可用。
 */
class AdminUserController extends Controller
{
    use ResolvesResident;

    public function index(): JsonResponse
    {
        $admins = Resident::where('is_admin', true)
            ->with('adminGrantedBy')
            ->orderByDesc('is_super_admin')
            ->orderBy('admin_granted_at')
            ->get();

        return response()->json([
            'data' => $admins->map(fn (Resident $admin): array => $this->present($admin))->values(),
        ]);
    }

    /**
     * 按手机号查出待授权的成员，返回身份供超管确认（不授权）。
     */
    public function candidate(Request $request): JsonResponse
    {
        $validated = $request->validate(['phone' => ['required', 'string']]);

        $resident = $this->findByPhone($validated['phone']);

        return response()->json(['data' => [
            'id' => $resident->id,
            'name' => $resident->displayName(),
        ]]);
    }

    /**
     * 超管确认身份后按 id 授权：手机号只用于查人，落权前经过一次人眼确认。
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(['resident_id' => ['required', 'integer']]);

        $resident = Resident::find($validated['resident_id']);

        if ($resident === null || $resident->is_admin) {
            throw ValidationException::withMessages(['resident_id' => '该成员不存在或已经是管理员']);
        }

        $resident->grantAdmin($this->resident($request));

        return response()->json(['data' => $this->present($resident->load('adminGrantedBy'))], 201);
    }

    public function destroy(Resident $resident): JsonResponse
    {
        // 超级管理员不在应用内收回（创始人由 CLI 管理）
        abort_if($resident->is_super_admin, 422, '超级管理员不能在这里收回');
        abort_unless($resident->is_admin, 404);

        $resident->revokeAdmin();

        return response()->json(['deleted' => true]);
    }

    private function findByPhone(string $phone): Resident
    {
        $resident = Resident::where('phone', $phone)->where('phone', '!=', '')->first();

        if ($resident === null) {
            throw ValidationException::withMessages(['phone' => '没有找到用这个手机号授权过的成员']);
        }

        if ($resident->is_admin) {
            throw ValidationException::withMessages(['phone' => '该成员已经是管理员']);
        }

        return $resident;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Resident $admin): array
    {
        return [
            'id' => $admin->id,
            'name' => $admin->displayName(),
            'phone' => $admin->phone,
            'is_super_admin' => $admin->is_super_admin,
            'granted_by' => $admin->adminGrantedBy?->displayName(),
            'granted_at' => $admin->admin_granted_at?->format('Y-m-d'),
        ];
    }
}
