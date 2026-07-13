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
            'empty_description' => 'AI 会整理你的明确选择、优先级、潜在矛盾和待确认问题。总结只依据你的答案，不替你做决定。',
            'risk_label' => '需要注意',
        ], array_intersect_key($configured, array_flip([
            'profile_label',
            'report_title',
            'empty_description',
            'risk_label',
        ])));
    }
}
