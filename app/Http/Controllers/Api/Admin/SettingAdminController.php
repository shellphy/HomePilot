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
    /**
     * 设置表单的分组与控件描述（kind：input 单行 / textarea 多行 / number 数字 / list 一行一项）。
     * 管理端按此渲染，新增设置字段只改后端：CommunitySettings 属性、settings 迁移、这里和 update 的规则。
     *
     * @var array<int, array{title: string, fields: array<int, array{key: string, label: string, kind: string}>}>
     */
    private const FORM_GROUPS = [
        [
            'title' => '社区身份',
            'fields' => [
                ['key' => 'name', 'label' => '小区名称（导航栏与分享标题用）', 'kind' => 'input'],
                ['key' => 'slogan', 'label' => '主口号', 'kind' => 'input'],
                ['key' => 'sub_slogan', 'label' => '副口号', 'kind' => 'input'],
            ],
        ],
        [
            'title' => '承诺与提示文案',
            'fields' => [
                ['key' => 'initiator_note', 'label' => '牵头人须知（发起页展示）', 'kind' => 'textarea'],
                ['key' => 'data_footnote', 'label' => '数据页脚注', 'kind' => 'textarea'],
                ['key' => 'admin_contact', 'label' => '管理员联系方式（认证引导展示）', 'kind' => 'textarea'],
                ['key' => 'ai_context', 'label' => '小区硬条件（AI 答疑的背景，如外机位/层高）', 'kind' => 'textarea'],
            ],
        ],
        [
            'title' => '选项清单（一行一项）',
            'fields' => [
                ['key' => 'buildings', 'label' => '楼栋（个人资料按此选择）', 'kind' => 'list'],
            ],
        ],
    ];

    public function show(CommunitySettings $settings): JsonResponse
    {
        return response()->json([
            'data' => $settings->toArray(),
            'groups' => self::FORM_GROUPS,
        ]);
    }

    public function update(Request $request, CommunitySettings $settings): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:30'],
            'slogan' => ['required', 'string', 'max:50'],
            'sub_slogan' => ['required', 'string', 'max:50'],
            'initiator_note' => ['required', 'string', 'max:500'],
            'data_footnote' => ['required', 'string', 'max:200'],
            'admin_contact' => ['required', 'string', 'max:200'],
            'ai_context' => ['nullable', 'string', 'max:500'],
            'buildings' => ['required', 'array', 'min:1'],
            'buildings.*' => ['required', 'string', 'max:10'],
        ]);

        // 唯一可留空的设置项：空字符串会被全局中间件转成 null，回填空串再存
        $validated['ai_context'] ??= '';

        $settings->fill($validated);
        $settings->save();

        return response()->json(['data' => $settings->toArray()]);
    }
}
