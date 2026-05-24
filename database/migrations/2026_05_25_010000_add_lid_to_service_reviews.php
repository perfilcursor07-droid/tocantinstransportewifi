<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_reviews', function (Blueprint $table) {
            // LID (Linked Device ID) do WhatsApp - identificador interno
            // do dispositivo, usado quando @lid não revela o número real
            $table->string('lid', 30)->nullable()->after('phone');
            $table->index('lid', 'service_reviews_lid_idx');
        });
    }

    public function down(): void
    {
        Schema::table('service_reviews', function (Blueprint $table) {
            $table->dropIndex('service_reviews_lid_idx');
            $table->dropColumn('lid');
        });
    }
};
