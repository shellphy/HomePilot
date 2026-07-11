<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * last_party_id = 上一次绑定的相关方：切回业主只解绑不删档案，
     * 再次入驻同类型身份时凭它找回原档案（资料与认证状态都在）。
     */
    public function up(): void
    {
        Schema::table('residents', function (Blueprint $table) {
            $table->foreignId('last_party_id')->nullable()->constrained('parties')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('residents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('last_party_id');
        });
    }
};
