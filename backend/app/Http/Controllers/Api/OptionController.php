<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class OptionController extends Controller
{
    /**
     * 登记表单的选项（户型/开工时间/品类），由后端统一下发，改配置不用发版小程序。
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'layouts' => config('homepilot.layouts'),
            'decoration_modes' => config('homepilot.decoration_modes'),
            'categories' => config('homepilot.categories'),
        ]);
    }
}
