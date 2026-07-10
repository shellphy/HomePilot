<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 记录：结构化表态的沉淀原子（谁、何时、就何事、做了什么表态）。
     * mode：register 登记（事前）/ join 接龙（事中承诺）/ review 评价（事后）/ 将来 vote 投票。
     * 装修档案 = matter_id NULL + mode=register + subject=renovation。
     * 唯一性（一户一份/一评）由应用层 updateOrCreate 保证——profile 记录的 matter_id 为 NULL，
     * SQLite 唯一索引对 NULL 互不冲突，数据库层约束不可靠。
     */
    public function up(): void
    {
        Schema::create('records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matter_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('party_id')->nullable()->constrained()->nullOnDelete(); // 评价对象为相关方时使用
            $table->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $table->string('mode', 20); // register / join / review / vote
            $table->string('subject', 30)->default(''); // 挂户不挂事务的登记用它区分，如 renovation
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['matter_id', 'mode']);
            $table->index(['resident_id', 'mode', 'subject']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('records');
    }
};
