<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 事务：社区正在处理的一件事（系统的中心运行时对象）。
     * type 决定装载的能力（状态机/表态模式/公示模板），类型专属字段入 payload。
     */
    public function up(): void
    {
        Schema::create('matters', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20); // groupbuy / notice / ...
            $table->foreignId('initiator_id')->nullable()->constrained('residents')->nullOnDelete();
            $table->foreignId('party_id')->nullable()->constrained()->nullOnDelete(); // 关联相关方（如成团商家）
            $table->string('title', 60);
            $table->string('category', 30)->default('');
            $table->string('state', 20); // 类型内状态机，由 MatterType 定义
            $table->boolean('is_approved')->default(false);
            $table->unsignedInteger('target_count')->default(0);
            $table->json('payload')->nullable(); // groupbuy: pitch/perk/terms/glossary/final_terms/final_note；notice: body
            $table->timestamps();

            $table->index(['type', 'is_approved']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matters');
    }
};
