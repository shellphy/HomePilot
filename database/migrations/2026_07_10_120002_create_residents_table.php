<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 成员：人通过与户的关系存在于社区中。
     * unit_label/room_label 是户的自报标签（如 "3栋"/"1802"）——隐私决定只收楼栋，
     * 将来验证体系上线、需要户级档案时再立表。
     * affiliated_party_id = 以哪个相关方身份出现（如商家入驻绑定 merchant 相关方）；业主为 NULL。
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
            $table->string('unit_label', 30)->default('');
            $table->foreignId('affiliated_party_id')->nullable()->constrained('parties')->nullOnDelete();
            $table->boolean('is_admin')->default(false);
            $table->string('room_label', 30)->default('');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('residents');
    }
};
