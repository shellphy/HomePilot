<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\ResidentResource;
use App\Models\Resident;
use App\Services\WeChat;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    public function login(LoginRequest $request, WeChat $weChat): JsonResponse
    {
        $openid = $weChat->openidFromCode($request->validated('code'));

        $resident = Resident::firstOrCreate(['openid' => $openid]);

        return response()->json([
            'token' => $resident->createToken('miniprogram')->plainTextToken,
            'resident' => ResidentResource::make($resident),
        ]);
    }
}
