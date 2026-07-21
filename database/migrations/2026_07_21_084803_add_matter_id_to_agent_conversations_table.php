<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_conversations', function (Blueprint $table) {
            $table->foreignId('matter_id')
                ->nullable()
                ->after('user_id')
                ->constrained('matters')
                ->nullOnDelete();
            $table->index(['user_id', 'matter_id'], 'agent_conversations_user_matter_index');
        });
    }

    public function down(): void
    {
        Schema::table('agent_conversations', function (Blueprint $table) {
            $table->dropIndex('agent_conversations_user_matter_index');
            $table->dropConstrainedForeignId('matter_id');
        });
    }
};
