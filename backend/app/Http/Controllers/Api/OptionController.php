<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Matters\MatterTypeRegistry;
use App\Models\Party;
use App\Settings\CommunitySettings;
use Illuminate\Http\JsonResponse;

class OptionController extends Controller
{
    /**
     * 静态配置统一下发：社区身份与文案、表单选项、可发起的事务类型。
     * 全部来自数据库 settings（小程序「小区管理 · 社区设置」可视化编辑），改配置不发版、不改代码。
     */
    public function index(CommunitySettings $settings): JsonResponse
    {
        return response()->json([
            'community' => [
                'name' => $settings->name,
                'app_name' => $settings->app_name,
                'slogan' => $settings->slogan,
                'sub_slogan' => $settings->sub_slogan,
                'pledge' => $settings->pledge,
                'initiator_note' => $settings->initiator_note,
                'initiate_hint' => $settings->initiate_hint,
                'data_footnote' => $settings->data_footnote,
            ],
            'layouts' => $settings->layouts,
            'decoration_modes' => $settings->decoration_modes,
            'categories' => $settings->categories,
            'party_types' => collect(Party::TYPES)
                ->map(fn (array $meta, string $key): array => [
                    'key' => $key,
                    'label' => $meta['label'],
                    'self_registrable' => $meta['self_registrable'],
                ])
                ->values(),
            'matter_types' => collect(MatterTypeRegistry::keys())
                ->map(fn (string $key): array => [
                    'key' => $key,
                    'label' => MatterTypeRegistry::for($key)->label(),
                    'user_initiatable' => MatterTypeRegistry::for($key)->userInitiatable(),
                ])
                ->values(),
        ]);
    }
}
