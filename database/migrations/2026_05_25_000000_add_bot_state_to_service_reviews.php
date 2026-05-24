<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_reviews', function (Blueprint $table) {
            // Estado do fluxo de avaliação via bot:
            // null = ainda não enviou
            // 'awaiting_rating' = enviou pergunta inicial, aguardando nota
            // 'awaiting_reason' = recebeu nota < 4, aguardando motivo
            // 'completed' = finalizado
            $table->string('bot_state', 30)->nullable()->after('whatsapp_status');
            $table->timestamp('bot_last_interaction_at')->nullable()->after('bot_state');
            
            $table->index(['phone', 'bot_state'], 'service_reviews_phone_state_idx');
        });
    }

    public function down(): void
    {
        Schema::table('service_reviews', function (Blueprint $table) {
            $table->dropIndex('service_reviews_phone_state_idx');
            $table->dropColumn(['bot_state', 'bot_last_interaction_at']);
        });
    }
};
