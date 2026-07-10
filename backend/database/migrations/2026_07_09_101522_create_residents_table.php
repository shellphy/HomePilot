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
        Schema::create('residents', function (Blueprint $table) {
            $table->id();
            $table->string('openid')->unique();
            $table->string('nickname')->default('');
            $table->string('unit_label')->default(''); // 楼栋-单元-室，如 "3-2-1801"，接龙对外展示
            $table->string('phone')->default(''); // 仅团长可见
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('residents');
    }
};
