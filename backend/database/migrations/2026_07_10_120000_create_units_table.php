<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 户：社区的空间原子与权利单位。粒度渐进——现在是楼栋（隐私决定不收房号），
     * 将来验证体系上线后可细化到户。
     */
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('label', 30)->unique(); // 如 "3栋"
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
