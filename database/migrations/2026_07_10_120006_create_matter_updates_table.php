<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 事项时间线：一件事的公开经过。
     * author_party_id 非空 = 被认证的治理类相关方（物业/开发商/业委会）的官方回应；空 = 牵头人的进展。
     */
    public function up(): void
    {
        Schema::create('matter_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_party_id')->nullable()->constrained('parties')->nullOnDelete();
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
