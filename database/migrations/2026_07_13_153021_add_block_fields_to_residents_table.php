<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('residents', function (Blueprint $table) {
            // 拉黑：被限制参与社区互动（提问/回复/接龙/发起/答问卷），仍可浏览
            $table->timestamp('blocked_at')->nullable()->after('admin_granted_at');
            $table->foreignId('blocked_by_id')->nullable()->after('blocked_at')->constrained('residents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('residents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('blocked_by_id');
            $table->dropColumn('blocked_at');
        });
    }
};
