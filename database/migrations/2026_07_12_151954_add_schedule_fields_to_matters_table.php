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
        Schema::table('matters', function (Blueprint $table) {
            $table->dateTime('starts_at')->nullable()->index();
            $table->dateTime('registration_deadline_at')->nullable()->index();
            $table->string('location', 120)->default('');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('matters', function (Blueprint $table) {
            $table->dropIndex(['starts_at']);
            $table->dropIndex(['registration_deadline_at']);
            $table->dropColumn(['starts_at', 'registration_deadline_at', 'location']);
        });
    }
};
