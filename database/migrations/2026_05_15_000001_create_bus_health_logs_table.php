<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bus_health_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bus_id')->constrained('buses')->cascadeOnDelete();
            $table->string('status', 20); // online, lagging, offline, unknown
            $table->integer('seconds_since_sync')->nullable();
            $table->string('public_ip', 45)->nullable();
            $table->integer('active_users')->default(0);
            $table->timestamp('recorded_at');

            $table->index(['bus_id', 'recorded_at']);
            $table->index('recorded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bus_health_logs');
    }
};
