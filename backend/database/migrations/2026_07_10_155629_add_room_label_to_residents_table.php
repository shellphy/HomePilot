<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 房号与楼栋分离：楼栋是户对象（对外公示粒度），房号是成员的私密补充信息（仅管理员可见）。
     */
    public function up(): void
    {
        Schema::table('residents', function (Blueprint $table) {
            $table->string('room_label', 30)->default('');
        });
    }

    public function down(): void
    {
        Schema::table('residents', function (Blueprint $table) {
            $table->dropColumn('room_label');
        });
    }
};
