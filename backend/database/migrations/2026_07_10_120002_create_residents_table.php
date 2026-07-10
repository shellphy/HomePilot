<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 成员：人通过与户的关系存在于社区中；商家身份=绑定一个 merchant 相关方。
     */
    public function up(): void
    {
        Schema::create('residents', function (Blueprint $table) {
            $table->id();
            $table->string('openid')->unique();
            $table->string('nickname', 30)->default('');
            $table->string('avatar')->default('');
            $table->string('wechat_id', 50)->default('');
            $table->string('phone', 20)->default('');
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('party_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('residents');
    }
};
