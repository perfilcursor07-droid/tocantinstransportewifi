<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bus_health_logs', function (Blueprint $table) {
            $table->unsignedInteger('latency_ms')->nullable()->after('active_users');
        });
    }

    public function down(): void
    {
        Schema::table('bus_health_logs', function (Blueprint $table) {
            $table->dropColumn('latency_ms');
        });
    }
};
