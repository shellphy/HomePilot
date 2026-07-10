<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 小区同期交付，所有人同期装修，"预计开工时间"是伪问题；换成"装修方式"。
     */
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn('start_timing');
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->string('decoration_mode')->default('')->after('layout');
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn('decoration_mode');
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->string('start_timing')->default('')->after('layout');
        });
    }
};
