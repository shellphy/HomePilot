<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 表态修订链：表态"只增不改"的技术兑现——修改表态时把旧 payload 存档，历史可查。
     */
    public function up(): void
    {
        Schema::create('stance_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stance_id')->constrained()->cascadeOnDelete();
            $table->json('payload');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stance_revisions');
    }
};
