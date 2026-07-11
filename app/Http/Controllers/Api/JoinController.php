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

        // 接龙名单是业主的信任背书：相关方不进名单（商家的参与方式是发起，治理方是官方回应）
        abort_if($resident->affiliated_party_id !== null, 403, '相关方身份不参与接龙，如需参与请在个人资料里切回业主身份');

        // 名单以「楼栋 + 昵称」公示，先选楼栋号才能上名单
        if ($resident->unit_label === '') {
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

        // 共享意愿的变更也是表态的一部分，同样走修订链——"只增不改"
        if (! $stance->wasRecentlyCreated && (bool) ($stance->payload['share_contact'] ?? false) !== $shareContact) {
            $stance->reviseTo(array_merge($stance->payload ?? [], ['share_contact' => $shareContact]));
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
