<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parties', function (Blueprint $table) {
            $table->string('review_status')->default('pending')->after('images');
            $table->string('reject_reason')->default('')->after('review_status');
        });

        DB::table('parties')->where('is_listed', true)->update(['review_status' => 'approved']);

        Schema::table('parties', function (Blueprint $table) {
            $table->dropColumn('is_listed');
        });
    }

    public function down(): void
    {
        Schema::table('parties', function (Blueprint $table) {
            $table->boolean('is_listed')->default(false)->after('images');
        });

        DB::table('parties')->where('review_status', 'approved')->update(['is_listed' => true]);

        Schema::table('parties', function (Blueprint $table) {
            $table->dropColumn(['review_status', 'reject_reason']);
        });
    }
};
