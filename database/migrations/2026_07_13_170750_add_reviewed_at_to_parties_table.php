<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parties', function (Blueprint $table) {
            // 身份核验通过的时间：档案上「已核验」附一个可追溯的日期
            $table->timestamp('reviewed_at')->nullable()->after('reject_reason');
        });
    }

    public function down(): void
    {
        Schema::table('parties', function (Blueprint $table) {
            $table->dropColumn('reviewed_at');
        });
    }
};
