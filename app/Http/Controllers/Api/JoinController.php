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

        // 接龙名单是业主的信任背书：相关方不进名单（商家的参与方式是发起，治理方是官方回应）
        abort_if($resident->affiliated_party_id !== null, 403, '相关方身份不参与接龙，如需参与请在个人资料里切回业主身份');

        $request->validate(['share_contact' => ['sometimes', 'boolean']]);
        $shareContact = $request->boolean('share_contact', true);

        $type = $matter->typeDef();

        // 已在名单里的只是变更共享意愿：接龙关闭后（如已成团）也允许——
        // 没共享的参与者事后想联系团长，得留一条自助补开共享的路
        $stance = $matter->joins()->whereBelongsTo($resident, 'resident')->first();

        if ($stance === null) {
            abort_unless($type->allowsJoin($matter), 422, '当前不能报名');

            // 名单以「楼栋 + 昵称」公示，先选楼栋号才能上名单
            if ($resident->unit_label === '') {
                throw ValidationException::withMessages([
                    'profile' => '报名前请先在「我的 · 个人资料」里选好楼栋号',
                ]);
            }

            // 承诺档位由类型按当前状态定（团购：意向征集/谈判中=登记意向，接龙中=确认参团）
            $stage = $type->joinStage($matter);

            $stance = $matter->joins()->firstOrCreate(
                ['resident_id' => $resident->id, 'mode' => Stance::MODE_JOIN],
                ['payload' => ['share_contact' => $shareContact] + ($stage !== null ? ['stage' => $stage] : [])],
            );
        }

        // 共享意愿的变更、意向升级为确认参团，都是表态的一部分，同样走修订链——"只增不改"
        if (! $stance->wasRecentlyCreated) {
            $revised = array_merge($stance->payload ?? [], ['share_contact' => $shareContact]);

            // 接龙开放期间重复报名视为升级承诺：登记过意向的这一下就是「确认参团」
            if ($type->allowsJoin($matter)
                && $stance->joinStageValue() === Stance::JOIN_STAGE_INTENT
                && $type->joinStage($matter) === Stance::JOIN_STAGE_CONFIRMED) {
                $revised['stage'] = Stance::JOIN_STAGE_CONFIRMED;
            }

            if ($revised !== ($stance->payload ?? [])) {
                $stance->reviseTo($revised);
            }
        }

        return response()->json([
            'joined' => true,
            'join_count' => $matter->joins()->count(),
        ], 201);
    }

    /**
     * 取消接龙。接龙关闭后（已成团/已有结果/已收场）名单就是事后依据
     * （成交名单、联系互通、评价资格都挂在上面），不能再自行退出——终态守卫保护的
     * 数据语义在这条路径上同样成立；确有变动找牵头人或管理员。
     */
    public function destroy(Request $request, Matter $matter): JsonResponse
    {
        abort_unless($matter->typeDef()->allowsJoin($matter), 422, '报名已截止，名单已封存不能自行退出；确有变动请联系牵头人或管理员');

        $matter->joins()->whereBelongsTo($this->resident($request), 'resident')->delete();

        return response()->json([
            'joined' => false,
            'join_count' => $matter->joins()->count(),
        ]);
    }
}
