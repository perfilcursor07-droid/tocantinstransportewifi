<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Marca quando enviamos o lembrete de pagamento pendente via WhatsApp
            $table->timestamp('unpaid_reminder_sent_at')->nullable()->after('paid_at');
            $table->index(['status', 'unpaid_reminder_sent_at', 'created_at'], 'payments_unpaid_reminder_idx');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_unpaid_reminder_idx');
            $table->dropColumn('unpaid_reminder_sent_at');
        });
    }
};
