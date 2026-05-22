<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Religar avaliações órfãs cujo telefone ainda existe em users
        DB::statement("
            UPDATE service_reviews sr
            INNER JOIN users u ON u.phone = sr.phone
            SET sr.user_id = u.id
            WHERE sr.user_id IS NULL
              AND sr.phone IS NOT NULL
              AND sr.phone != ''
        ");

        // 2. Remover avaliações órfãs sem possibilidade de religação
        DB::statement("DELETE FROM service_reviews WHERE user_id IS NULL");

        // 3. Trocar FK: agora deletar usuário deleta avaliação automaticamente
        Schema::table('service_reviews', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')
                ->references('id')->on('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('service_reviews', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')
                ->references('id')->on('users')
                ->nullOnDelete();
        });
    }
};
