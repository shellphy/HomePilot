<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('initiator_id')->nullable()->after('id')
                ->constrained('residents')->nullOnDelete(); // 发起人即该项目的团长
            $table->boolean('is_approved')->default(false)->index()->after('status'); // 管理员审核后才对外展示
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('initiator_id');
            $table->dropColumn('is_approved');
        });
    }
};
