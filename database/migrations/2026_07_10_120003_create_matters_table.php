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
            // 审核状态（与 state 正交）：pending 待审核 / approved 已公示 / rejected 已驳回
            $table->string('review_status', 20)->default('pending');
            // 驳回理由：仅 rejected 态有值，发起人在详情页看到，编辑重提后清空
            $table->string('reject_reason', 200)->default('');
            $table->unsignedInteger('target_count')->default(0);
            $table->json('payload')->nullable(); // groupbuy: pitch/perk/terms/glossary/final_terms/final_note；notice: body
            // 事项之间的从属关联：征集挂到团购上（配套摸底问卷），团购详情页展示问卷入口
            $table->foreignId('related_matter_id')->nullable()->constrained('matters')->nullOnDelete();
            // 站内未读信号：最近一次对参与者/发起人有意义的动态（审核/流转/公示/进展），
            // 与成员的已读时间比对得出「我的」页红点；resident_id 用于排除自己触发的动态
            $table->timestamp('last_activity_at')->nullable();
            $table->foreignId('last_activity_resident_id')->nullable()->constrained('residents')->nullOnDelete();
            $table->timestamps();
            // 软删除：误删可从库里恢复，表态/动态一并保留（级联只在真删时发生）
            $table->softDeletes();

            $table->index(['type', 'review_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matters');
    }
};
