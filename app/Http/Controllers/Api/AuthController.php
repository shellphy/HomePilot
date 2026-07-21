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

        $resident = Resident::updateOrCreate(['unionid' => $unionid], ['openid_mp' => $openid]);

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
        /** @var PersonalAccessToken $token */
        $token = $request->user('sanctum')->currentAccessToken();
        $token->delete();

        Log::info('退出登录', ['resident_id' => $request->user('sanctum')->getAuthIdentifier()]);

        return response()->json(['message' => '已退出登录']);
    }
}
