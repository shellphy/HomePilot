<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Concerns\ResolvesResident;
use App\Http\Controllers\Controller;
use App\Models\Resident;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * 管理端 · 拉黑名单：查看、拉黑、解除。被拉黑的成员仍可浏览，但不能参与社区互动。
 */
class AdminBlockController extends Controller
{
    use ResolvesResident;

    public function index(): JsonResponse
    {
        $blocked = Resident::whereNotNull('blocked_at')
            ->with('blockedBy')
            ->orderByDesc('blocked_at')
            ->get();

        return response()->json([
            'data' => $blocked->map(fn (Resident $resident): array => $this->present($resident))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(['resident_id' => ['required', 'integer']]);

        $resident = Resident::find((int) $validated['resident_id']);

        if ($resident === null) {
            throw ValidationException::withMessages(['resident_id' => '成员不存在']);
        }

        if ($resident->is_admin) {
            throw ValidationException::withMessages(['resident_id' => '不能拉黑管理员']);
        }

        if ($resident->isBlocked()) {
            throw ValidationException::withMessages(['resident_id' => '该成员已经在拉黑名单里']);
        }

        $resident->block($this->resident($request));

        return response()->json(['data' => $this->present($resident->load('blockedBy'))], 201);
    }

    public function destroy(Resident $resident): JsonResponse
    {
        abort_unless($resident->isBlocked(), 404);

        $resident->unblock();

        return response()->json(['deleted' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Resident $resident): array
    {
        return [
            'id' => $resident->id,
            'name' => $resident->displayName(),
            'blocked_by' => $resident->blockedBy?->displayName(),
            'blocked_at' => $resident->blocked_at?->format('Y-m-d'),
        ];
    }
}
