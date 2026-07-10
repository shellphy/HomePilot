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
        Schema::create('registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resident_id')->unique()->constrained()->cascadeOnDelete(); // 一户一份登记
            $table->string('layout'); // 户型
            $table->string('start_timing'); // 预计开工时间
            $table->json('interests'); // 感兴趣的团购品类
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registrations');
    }
};
