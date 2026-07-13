<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matter_questions', function (Blueprint $table) {
            // 回复人（供管理员拉黑定位）；answered_by 仍是署名快照，二者并存
            $table->foreignId('answered_by_id')->nullable()->after('answered_by')->constrained('residents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('matter_questions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('answered_by_id');
        });
    }
};
