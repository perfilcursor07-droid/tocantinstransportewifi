<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->string('mikrotik_id', 30)->nullable(); // Serial do MikroTik (ex: HH60A2NSBE7)
            $table->string('bus_number', 10)->nullable(); // Número do carro (ex: 5013)
            $table->date('scheduled_date')->nullable(); // Data planejada para atendimento
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->text('resolution')->nullable(); // O que foi feito para resolver
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index(['status', 'scheduled_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_tickets');
    }
};
