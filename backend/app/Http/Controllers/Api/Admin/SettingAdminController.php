<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Settings\CommunitySettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 管理端 · 社区设置。
 * 名称/口号/承诺文案/户型/品类等全部在这里改，不发版、不改代码。
 */
class SettingAdminController extends Controller
{
    public function show(CommunitySettings $settings): JsonResponse
    {
        return response()->json(['data' => $settings->toArray()]);
    }

    public function update(Request $request, CommunitySettings $settings): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:30'],
            'app_name' => ['required', 'string', 'max:30'],
            'slogan' => ['required', 'string', 'max:50'],
            'sub_slogan' => ['required', 'string', 'max:50'],
            'pledge' => ['required', 'string', 'max:500'],
            'initiator_note' => ['required', 'string', 'max:500'],
            'initiate_hint' => ['required', 'string', 'max:500'],
            'data_footnote' => ['required', 'string', 'max:200'],
            'total_households' => ['required', 'integer', 'min:1'],
            'layouts' => ['required', 'array', 'min:1'],
            'layouts.*' => ['required', 'string', 'max:50'],
            'decoration_modes' => ['required', 'array', 'min:1'],
            'decoration_modes.*' => ['required', 'string', 'max:20'],
            'categories' => ['required', 'array', 'min:1'],
            'categories.*' => ['required', 'string', 'max:20'],
        ]);

        $settings->fill($validated);
        $settings->save();

        return response()->json(['data' => $settings->toArray()]);
    }
}
