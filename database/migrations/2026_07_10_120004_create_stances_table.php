<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 表态：结构化表态的沉淀原子（谁、何时、就哪件事项、做了什么表态）。
     * mode：register 登记（事前申报）/ join 接龙（事中承诺）/ review 评价（事后判断）。
     * 表态必须挂在一件事项上；唯一性（一事一户一份）由应用层
     * firstOrCreate/updateOrCreate 保证，唯一索引兜底并发。
     */
    public function up(): void
    {
        Schema::create('stances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $table->string('mode', 20); // register / join / review
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['matter_id', 'resident_id', 'mode']);
            $table->index(['matter_id', 'mode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stances');
    }
};
