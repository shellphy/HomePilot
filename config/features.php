<?php

return [
    // 未备案的 AI 能力默认关闭。
    'ai' => [
        'chat' => env('AI_CHAT_ENABLED', false),
        'census_report' => env('AI_CENSUS_REPORT_ENABLED', false),
        'glossary_draft' => env('AI_GLOSSARY_DRAFT_ENABLED', false),
    ],

];
