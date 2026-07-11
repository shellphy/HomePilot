<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 成员：人通过与户的关系存在于社区中。
     * unit_label 从社区设置的楼栋清单里选；room_label 是自报房号（仅管理员可见）。
     * phone 只能通过微信手机号授权组件写入（服务端 code 换真实绑定号码），是唯一的联系字段。
     * affiliated_party_id = 以哪个相关方身份出现（如商家入驻绑定 merchant 相关方）；业主为 NULL。
     */
    public function up(): void
    {
        Schema::create('residents', function (Blueprint $table) {
            $table->id();
            $table->string('openid')->unique();
            $table->string('nickname', 30)->default('');
            $table->string('avatar')->default('');
            $table->string('phone', 20)->default('');
            $table->string('unit_label', 30)->default('');
            $table->foreignId('affiliated_party_id')->nullable()->constrained('parties')->nullOnDelete();
            // last_party_id = 我的相关方档案（切回业主也记着，再入驻原样找回）
            $table->foreignId('last_party_id')->nullable()->constrained('parties')->nullOnDelete();
            $table->boolean('is_admin')->default(false);
            $table->string('room_label', 30)->default('');
            // 「我牵头的 / 我参与的」两类列表的已读时间：与事项的 last_activity_at 比对出红点
            $table->timestamp('mine_seen_at')->nullable();
            $table->timestamp('joined_seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('residents');
    }
};
