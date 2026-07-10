<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 相关方：与社区发生关系的组织（商家/物业/开发商/业委会）。
     * 不是"用户"，是对手方与服务方，拥有累积的信用档案。
     */
    public function up(): void
    {
        Schema::create('parties', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20); // merchant / property / developer / committee
            $table->string('name', 50);
            $table->string('category', 30)->default(''); // 商家主营品类
            $table->boolean('is_listed')->default(false); // 管理员认证后进入公示商家名单
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parties');
    }
};
