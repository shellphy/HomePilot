<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 事项之间的从属关联：目前用于征集挂到团购上（团购的意向征集期
     * 配套一份品类摸底问卷），团购详情页展示问卷入口，结果给团长当谈判依据。
     */
    public function up(): void
    {
        Schema::table('matters', function (Blueprint $table) {
            $table->foreignId('related_matter_id')
                ->nullable()
                ->constrained('matters')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('matters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('related_matter_id');
        });
    }
};
