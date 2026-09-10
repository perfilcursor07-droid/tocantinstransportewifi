<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interval_access_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->date('access_date');
            $table->dateTime('started_at');
            $table->dateTime('expires_at')->index();
            $table->timestamps();
            $table->unique(['user_id', 'access_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interval_access_days');
    }
};
