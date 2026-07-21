<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI 能力开关
    |--------------------------------------------------------------------------
    |
    | 按能力控制小程序端 AI 入口与对应后端接口。默认全关，备案通过的能力
    | 在环境变量里逐项打开。
    |
    */

    'ai' => [
        'chat' => env('AI_CHAT_ENABLED', false),
        'census_report' => env('AI_CENSUS_REPORT_ENABLED', false),
        'glossary_draft' => env('AI_GLOSSARY_DRAFT_ENABLED', false),
    ],

];
