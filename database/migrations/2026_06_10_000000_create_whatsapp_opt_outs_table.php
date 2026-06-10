<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabela de descadastro (opt-out) do WhatsApp.
     *
     * Quando um passageiro responde "PARAR" (ou similar), o telefone é
     * registrado aqui e passa a ser filtrado de TODOS os envios não-transacionais
     * (avaliação e lembretes de marketing). Isso reduz drasticamente denúncias,
     * que são o maior gerador de banimento de número.
     */
    public function up(): void
    {
        Schema::create('whatsapp_opt_outs', function (Blueprint $table) {
            $table->id();
            // Telefone normalizado (somente dígitos) e os últimos 8 dígitos para
            // casamento robusto (ignora código do país e o "9" variável).
            $table->string('phone', 20);
            $table->string('phone_last8', 8)->index();
            $table->string('source', 30)->default('keyword'); // keyword | manual | admin
            $table->string('keyword', 60)->nullable();        // palavra que disparou (ex.: "PARAR")
            $table->timestamp('opted_out_at');
            $table->timestamps();

            $table->unique('phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_opt_outs');
    }
};
