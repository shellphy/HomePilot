<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesResident;
use App\Http\Controllers\Controller;
use App\Models\Matter;
use App\Models\Stance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class JoinController extends Controller
{
    use ResolvesResident;

    /**
     * 接龙表态（幂等：重复报名不会产生第二条，但会更新共享意愿）。
     * share_contact = 进入联系方式互通阶段后（如成团）愿意和牵头人互通手机号。
     */
    public function store(Request $request, Matter $matter): JsonResponse
    {
        $resident = $this->resident($request);

        abort_unless($matter->typeDef()->allowsJoin($matter), 422, '当前不能报名');

        // 名单以「楼栋 + 昵称」公示，业主先选楼栋号才能上名单（相关方以入驻身份出现，不受此限）
        if ($resident->affiliated_party_id === null && $resident->unit_label === '') {
            throw ValidationException::withMessages([
                'profile' => '报名前请先在「我的 · 个人资料」里选好楼栋号',
            ]);
        }

        $request->validate(['share_contact' => ['sometimes', 'boolean']]);
        $shareContact = $request->boolean('share_contact', true);

        $stance = $matter->joins()->firstOrCreate(
            ['resident_id' => $resident->id, 'mode' => Stance::MODE_JOIN],
            ['payload' => ['share_contact' => $shareContact]],
        );

        if (! $stance->wasRecentlyCreated) {
            $stance->update(['payload' => array_merge($stance->payload ?? [], ['share_contact' => $shareContact])]);
        }

        return response()->json([
            'joined' => true,
            'join_count' => $matter->joins()->count(),
        ], 201);
    }

    /**
     * 取消接龙。
     */
    public function destroy(Request $request, Matter $matter): JsonResponse
    {
        $matter->joins()->whereBelongsTo($this->resident($request), 'resident')->delete();

        return response()->json([
            'joined' => false,
            'join_count' => $matter->joins()->count(),
        ]);
    }
}
