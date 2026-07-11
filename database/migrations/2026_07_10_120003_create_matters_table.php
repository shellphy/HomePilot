<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 事项：社区正在处理的一件事（系统的中心运行时对象）。
     * type 决定装载的能力（状态机/表态模式/公示模板），类型专属字段入 payload。
     */
    public function up(): void
    {
        Schema::create('matters', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20); // groupbuy / notice / ...
            $table->foreignId('initiator_id')->nullable()->constrained('residents')->nullOnDelete();
            // 发起时的相关方身份快照（已认证商家发起的团带商家署名；成员之后切换身份不影响历史事项）
            $table->foreignId('initiator_party_id')->nullable()->constrained('parties')->nullOnDelete();
            $table->string('title', 60);
            $table->string('category', 30)->default('');
            $table->string('state', 20); // 类型内状态机，由 MatterType 定义
            $table->boolean('is_approved')->default(false);
            $table->unsignedInteger('target_count')->default(0);
            $table->json('payload')->nullable(); // groupbuy: pitch/perk/terms/glossary/final_terms/final_note；notice: body
            $table->timestamps();
            // 软删除：误删可从库里恢复，表态/动态一并保留（级联只在真删时发生）
            $table->softDeletes();

            $table->index(['type', 'is_approved']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matters');
    }
};
