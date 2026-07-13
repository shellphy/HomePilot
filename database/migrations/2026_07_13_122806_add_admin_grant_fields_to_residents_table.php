<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('residents', function (Blueprint $table) {
            // 超级管理员：唯一能在应用内增减管理员的角色（创始人由 CLI 种下）
            $table->boolean('is_super_admin')->default(false)->after('is_admin');
            // 授权审计：这个管理员是谁授权的、何时授权（CLI 种下的为 null）
            $table->foreignId('admin_granted_by_id')->nullable()->after('is_super_admin')->constrained('residents')->nullOnDelete();
            $table->timestamp('admin_granted_at')->nullable()->after('admin_granted_by_id');
        });
    }

    public function down(): void
    {
        Schema::table('residents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('admin_granted_by_id');
            $table->dropColumn(['is_super_admin', 'admin_granted_at']);
        });
    }
};
