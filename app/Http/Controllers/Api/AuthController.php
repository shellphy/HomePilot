<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\ResidentResource;
use App\Models\Resident;
use App\Services\WeChat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function login(LoginRequest $request, WeChat $weChat): JsonResponse
    {
        ['openid' => $openid, 'unionid' => $unionid] = $weChat->sessionFromCode($request->validated('code'));

        // 服务号 H5 先认识的人再打开小程序，认到同一个 unionid 上并补齐 openid_mp
        $resident = Resident::updateOrCreate(['unionid' => $unionid], ['openid_mp' => $openid]);

        // unionid/openid 是身份凭证，不进日志
        Log::info('登录成功', [
            'resident_id' => $resident->id,
            'registered' => $resident->wasRecentlyCreated,
        ]);

        return response()->json([
            'token' => $resident->createToken('miniprogram')->plainTextToken,
            'resident' => ResidentResource::make($resident),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user('sanctum')?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json(['message' => '已退出登录']);
    }
}
