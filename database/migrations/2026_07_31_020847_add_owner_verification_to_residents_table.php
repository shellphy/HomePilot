<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('residents', function (Blueprint $table) {
            $table->timestamp('owner_verified_at')->nullable();
            $table->foreignId('owner_verified_by_id')->nullable()->constrained('residents')->nullOnDelete();
            $table->string('owner_invitation_code', 8)->nullable()->unique();
            $table->timestamp('owner_invitation_code_expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('residents', function (Blueprint $table) {
            $table->dropUnique(['owner_invitation_code']);
            $table->dropConstrainedForeignId('owner_verified_by_id');
            $table->dropColumn([
                'owner_verified_at',
                'owner_invitation_code',
                'owner_invitation_code_expires_at',
            ]);
        });
    }
};
