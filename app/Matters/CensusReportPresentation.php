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
            'empty_description' => 'AI 会把你的答案整理成重点、方向和待确认的问题。',
        ], array_intersect_key($configured, array_flip([
            'empty_description',
        ])));
    }
}
