<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('residents', function (Blueprint $table) {
            $table->string('avatar')->default('')->after('nickname'); // 微信头像（chooseAvatar 上传后的 url）
            $table->string('wechat_id')->default('')->after('phone'); // 微信号，团购联系用（必填），手机号改为选填
            $table->string('role')->default('resident')->index()->after('wechat_id'); // resident | merchant
            $table->string('merchant_name')->default('')->after('role');
            $table->string('merchant_category')->default('')->after('merchant_name');
        });
    }

    public function down(): void
    {
        Schema::table('residents', function (Blueprint $table) {
            $table->dropColumn(['avatar', 'wechat_id', 'role', 'merchant_name', 'merchant_category']);
        });
    }
};
