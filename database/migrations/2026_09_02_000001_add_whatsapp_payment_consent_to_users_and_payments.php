<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('whatsapp_payment_opt_in_at')->nullable()->after('phone');
            $table->string('whatsapp_payment_opt_in_phone', 20)->nullable()->after('whatsapp_payment_opt_in_at');
            $table->string('whatsapp_payment_opt_in_source', 50)->nullable()->after('whatsapp_payment_opt_in_phone');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->timestamp('whatsapp_confirmation_sent_at')->nullable()->after('unpaid_reminder_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('whatsapp_confirmation_sent_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'whatsapp_payment_opt_in_at',
                'whatsapp_payment_opt_in_phone',
                'whatsapp_payment_opt_in_source',
            ]);
        });
    }
};
