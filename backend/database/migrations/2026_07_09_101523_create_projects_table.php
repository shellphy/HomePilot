<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('category');
            $table->string('title');
            $table->string('status')->default('seeking')->index();
            $table->unsignedInteger('target_households')->default(0);
            $table->text('pitch')->nullable(); // 团长的话
            $table->json('terms')->nullable(); // 团购条件 [{label, value}]
            $table->string('perk')->default(''); // 阶梯优惠，如 "满 20 户赠全屋水电升级"
            $table->json('glossary')->nullable(); // 买前必懂 [{term, explain}]
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
