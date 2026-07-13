<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 「大家都在问」：针对某个事项的公开问答。
     * 不是评论区——只有业主提问和负责方（团长/商家/管理员）回答两种内容，
     * 业主之间不互相回复（闲聊留在微信群），答案沉淀给后来的人。
     * echoes 表是「同问 +1」：重复的疑问聚合成热度信号，而不是刷屏。
     */
    public function up(): void
    {
        Schema::create('matter_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $table->string('content', 300);
            $table->text('answer')->nullable();
            // 回答方署名快照（团长昵称/商家名/「管理员」），断开后续改名的耦合
            $table->string('answered_by', 60)->default('');
            // 回复人：供管理员拉黑与本人删除定位（answered_by 是署名快照）
            $table->foreignId('answered_by_id')->nullable()->constrained('residents')->nullOnDelete();
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();
        });

        Schema::create('matter_question_echoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matter_question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['matter_question_id', 'resident_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matter_question_echoes');
        Schema::dropIfExists('matter_questions');
    }
};
