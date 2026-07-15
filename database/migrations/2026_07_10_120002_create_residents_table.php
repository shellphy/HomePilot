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
            // 认人只认 unionid：同一个人在小程序和服务号下的 openid 不同，
            // 只有开放平台账号下的 unionid 跨端唯一
            $table->string('unionid')->unique();
            // 小程序订阅消息的 touser 要的是小程序这一端的 openid
            $table->string('openid_mp')->default('');
            $table->string('nickname', 30)->default('');
            $table->string('avatar')->default('');
            $table->string('phone', 20)->default('');
            $table->string('unit_label', 30)->default('');
            $table->foreignId('affiliated_party_id')->nullable()->constrained('parties')->nullOnDelete();
            // last_party_id = 我的相关方档案（切回业主也记着，再入驻原样找回）
            $table->foreignId('last_party_id')->nullable()->constrained('parties')->nullOnDelete();
            $table->boolean('is_admin')->default(false);
            // 超级管理员：唯一能在应用内增减管理员的角色（创始人由 CLI 种下）
            $table->boolean('is_super_admin')->default(false);
            // 授权审计：这个管理员是谁授权的、何时授权（CLI 种下的为 null）
            $table->foreignId('admin_granted_by_id')->nullable()->constrained('residents')->nullOnDelete();
            $table->timestamp('admin_granted_at')->nullable();
            // 拉黑：被限制参与社区互动（提问/回复/接龙/发起/答问卷），仍可浏览
            $table->timestamp('blocked_at')->nullable();
            $table->foreignId('blocked_by_id')->nullable()->constrained('residents')->nullOnDelete();
            $table->string('room_label', 30)->default('');
            $table->string('layout_label', 30)->default(''); // 户型（从社区设置的户型清单里选，如 107㎡）
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
