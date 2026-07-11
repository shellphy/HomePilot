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
            // 自我介绍（各类型统一，内容自由发挥）：intro 一句话（名录列表行），
            // description 详细介绍（详情页：商家写地址/服务，物业写哪些问题找他们），images 照片（门头/资质/现场）
            $table->string('intro', 60)->default('');
            $table->text('description')->nullable();
            $table->json('images')->nullable();
            $table->boolean('is_listed')->default(false); // 管理员认证后进入公示商家名单
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parties');
    }
};
