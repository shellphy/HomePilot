<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * 小区硬条件（AI 答疑的背景知识）：外机位、层高、水电这类只有本小区才知道的约束。
     * 消费方：MatterExplainer（业主侧 AI 答疑）的上下文注入。
     */
    public function up(): void
    {
        $this->migrator->add(
            'community.ai_context',
            '每户只有一个空调外机位，基本只能装中央空调（一拖多）；层高约 2.95 米，吊顶后约 2.6 米。',
        );
    }
};
