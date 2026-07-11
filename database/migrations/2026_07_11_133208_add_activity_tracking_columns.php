<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 站内未读信号：事项记录最近一次对参与者/发起人有意义的动态
     * （审核结果、状态流转、成交公示、时间线进展），成员记录两类列表的已读时间，
     * 两者比对得出「我的」页红点。last_activity_resident_id 用于排除自己触发的动态。
     */
    public function up(): void
    {
        Schema::table('matters', function (Blueprint $table) {
            $table->timestamp('last_activity_at')->nullable();
            $table->foreignId('last_activity_resident_id')->nullable()->constrained('residents')->nullOnDelete();
        });

        Schema::table('residents', function (Blueprint $table) {
            $table->timestamp('mine_seen_at')->nullable();
            $table->timestamp('joined_seen_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('matters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('last_activity_resident_id');
            $table->dropColumn('last_activity_at');
        });

        Schema::table('residents', function (Blueprint $table) {
            $table->dropColumn(['mine_seen_at', 'joined_seen_at']);
        });
    }
};
