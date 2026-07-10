<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 事务时间线：一件事的公开经过（原"团购进度更新"的泛化）。
     */
    public function up(): void
    {
        Schema::create('matter_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matter_id')->constrained()->cascadeOnDelete();
            $table->date('happened_on');
            $table->string('content', 500);
            $table->json('images')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matter_updates');
    }
};
