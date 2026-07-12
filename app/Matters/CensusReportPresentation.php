<?php

namespace App\Matters;

use App\Models\Matter;

class CensusReportPresentation
{
    /** @return array<string, string> */
    public function for(Matter $matter): array
    {
        $configured = $matter->payloadValue('report_presentation', []);
        $configured = is_array($configured) ? $configured : [];

        return array_merge([
            'profile_label' => '你的问卷总结',
            'report_title' => '我的问卷总结',
            'empty_description' => 'AI 会整理你的明确选择、优先级、潜在矛盾、待确认问题和沟通清单。总结只依据你的答案，不替你做决定。',
            'risk_label' => '需要注意',
            'brief_label' => '给相关方的沟通清单',
            'share_button_label' => '分享给相关方',
            'share_disclaimer' => '这是一份用户主动分享的只读问卷总结，请以后续正式确认结果为准。',
        ], array_intersect_key($configured, array_flip([
            'profile_label',
            'report_title',
            'empty_description',
            'risk_label',
            'brief_label',
            'share_button_label',
            'share_disclaimer',
        ])));
    }
}
