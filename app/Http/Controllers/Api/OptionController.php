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
     * 静态配置统一下发：社区身份与文案、表单选项、可发起的事项类型、AI 能力开关。
     * 除 ai 外均来自数据库 settings（小程序「小区管理 · 社区设置」可视化编辑），改配置不发版、不改代码。
     * ai 来自环境变量，随部署固定。
     */
    public function index(CommunitySettings $settings): JsonResponse
    {
        return response()->json([
            'ai' => [
                'chat' => (bool) config('features.ai.chat'),
                'census_report' => (bool) config('features.ai.census_report'),
                'glossary_draft' => (bool) config('features.ai.glossary_draft'),
            ],
            'community' => [
                'name' => $settings->name,
                'slogan' => $settings->slogan,
                'sub_slogan' => $settings->sub_slogan,
                'initiator_note' => $settings->initiator_note,
                'admin_contact' => $settings->admin_contact,
            ],
            'buildings' => $settings->buildings,
            'layouts' => $settings->layouts,
            'party_types' => collect(Party::TYPES)
                ->map(fn (array $meta, string $key): array => [
                    'key' => $key,
                    'label' => $meta['label'],
                    'self_registrable' => $meta['self_registrable'],
                    'name_hint' => $meta['name_hint'],
                    // 空 = 该类型没有档案补充字段（主营品类只对商家有意义）
                    'category_label' => $meta['category_label'],
                    'description_hint' => $meta['description_hint'],
                ])
                ->values(),
            'matter_types' => collect(MatterTypeRegistry::keys())
                ->map(fn (string $key): array => [
                    'key' => $key,
                    'label' => MatterTypeRegistry::for($key)->label(),
                    'user_initiatable' => MatterTypeRegistry::for($key)->userInitiatable(),
                    'merchant_initiatable' => MatterTypeRegistry::for($key)->merchantInitiatable(),
                ])
                ->values(),
        ]);
    }
}
