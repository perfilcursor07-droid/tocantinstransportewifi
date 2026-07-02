<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_pix_registration_links', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->string('label')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('uses_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('driver_pix_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_link_id')->nullable()->constrained('driver_pix_registration_links')->nullOnDelete();
            $table->string('full_name');
            $table->string('pix_key');
            $table->enum('pix_key_type', ['cpf', 'cnpj', 'email', 'phone', 'random'])->default('random');
            $table->string('bus_number', 20);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('rejected_reason')->nullable();
            $table->text('admin_notes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'bus_number']);
        });

        Schema::create('driver_pix_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_pix_profile_id')->constrained('driver_pix_profiles')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('description')->nullable();
            $table->enum('status', ['pending', 'paid', 'cancelled'])->default('pending');
            $table->string('payment_reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_pix_payments');
        Schema::dropIfExists('driver_pix_profiles');
        Schema::dropIfExists('driver_pix_registration_links');
    }
};
