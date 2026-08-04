<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('driver_pix_profiles')) {
            return;
        }

        // CPF passa a ser o identificador usado pelo motorista para localizar o cadastro
        if (! Schema::hasColumn('driver_pix_profiles', 'cpf')) {
            Schema::table('driver_pix_profiles', function (Blueprint $table) {
                $table->string('cpf', 14)->nullable()->after('full_name');
                $table->index('cpf');
            });
        }

        if (! Schema::hasColumn('driver_pix_profiles', 'last_confirmed_month')) {
            Schema::table('driver_pix_profiles', function (Blueprint $table) {
                $table->date('last_confirmed_month')->nullable()->after('bus_number');
            });
        }

        // Cadastro por competencia (mes): guarda o onibus e a chave PIX vigentes no mes
        if (! Schema::hasTable('driver_pix_profile_months')) {
            Schema::create('driver_pix_profile_months', function (Blueprint $table) {
                $table->id();
                $table->foreignId('driver_pix_profile_id')->constrained('driver_pix_profiles')->cascadeOnDelete();
                $table->date('reference_month');
                $table->string('bus_number', 20);
                $table->string('pix_key');
                $table->enum('pix_key_type', ['cpf', 'cnpj', 'email', 'phone', 'random'])->default('random');
                $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
                $table->boolean('is_update')->default(false);
                $table->json('changed_fields')->nullable();
                $table->string('source', 20)->default('driver');
                $table->foreignId('registration_link_id')->nullable()->constrained('driver_pix_registration_links')->nullOnDelete();
                $table->timestamp('submitted_at')->nullable();
                $table->text('rejected_reason')->nullable();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();

                $table->unique(['driver_pix_profile_id', 'reference_month', 'bus_number'], 'driver_pix_month_unique');
                $table->index(['reference_month', 'status']);
            });
        }

        if (Schema::hasTable('driver_pix_payments')) {
            Schema::table('driver_pix_payments', function (Blueprint $table) {
                if (! Schema::hasColumn('driver_pix_payments', 'reference_month')) {
                    $table->date('reference_month')->nullable()->after('driver_pix_profile_id');
                    $table->index('reference_month');
                }

                if (! Schema::hasColumn('driver_pix_payments', 'bus_number')) {
                    $table->string('bus_number', 20)->nullable()->after('reference_month');
                }

                if (! Schema::hasColumn('driver_pix_payments', 'driver_pix_profile_month_id')) {
                    $table->foreignId('driver_pix_profile_month_id')
                        ->nullable()
                        ->after('bus_number')
                        ->constrained('driver_pix_profile_months')
                        ->nullOnDelete();
                }
            });
        }

        $this->backfill();
    }

    /**
     * Dados legados: cria a competencia dos cadastros existentes e
     * define o mes/onibus dos pagamentos ja registrados.
     */
    private function backfill(): void
    {
        $profiles = DB::table('driver_pix_profiles')
            ->select('id', 'bus_number', 'pix_key', 'pix_key_type', 'status', 'created_at', 'approved_by', 'approved_at', 'registration_link_id')
            ->get();

        if ($profiles->isEmpty()) {
            return;
        }

        $now = now();
        $busByProfile = [];

        foreach ($profiles as $profile) {
            $busByProfile[$profile->id] = $profile->bus_number;

            $createdAt = $profile->created_at ? \Illuminate\Support\Carbon::parse($profile->created_at) : $now;
            $month = $createdAt->copy()->startOfMonth()->toDateString();

            DB::table('driver_pix_profile_months')->insertOrIgnore([
                'driver_pix_profile_id' => $profile->id,
                'reference_month' => $month,
                'bus_number' => $profile->bus_number,
                'pix_key' => $profile->pix_key,
                'pix_key_type' => $profile->pix_key_type ?: 'random',
                'status' => $profile->status ?: 'pending',
                'is_update' => false,
                'source' => 'legacy',
                'registration_link_id' => $profile->registration_link_id,
                'submitted_at' => $createdAt,
                'approved_by' => $profile->approved_by,
                'approved_at' => $profile->approved_at,
                'created_at' => $createdAt,
                'updated_at' => $now,
            ]);

            if ($profile->status === 'approved') {
                DB::table('driver_pix_profiles')
                    ->where('id', $profile->id)
                    ->update(['last_confirmed_month' => $month]);
            }
        }

        DB::table('driver_pix_payments')
            ->whereNull('reference_month')
            ->orderBy('id')
            ->select('id', 'driver_pix_profile_id', 'created_at', 'paid_at')
            ->chunkById(300, function ($payments) use ($busByProfile) {
                foreach ($payments as $payment) {
                    $base = $payment->paid_at ?: $payment->created_at;
                    $month = $base
                        ? \Illuminate\Support\Carbon::parse($base)->startOfMonth()->toDateString()
                        : now()->startOfMonth()->toDateString();

                    DB::table('driver_pix_payments')->where('id', $payment->id)->update([
                        'reference_month' => $month,
                        'bus_number' => $busByProfile[$payment->driver_pix_profile_id] ?? null,
                    ]);
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasTable('driver_pix_payments')) {
            Schema::table('driver_pix_payments', function (Blueprint $table) {
                if (Schema::hasColumn('driver_pix_payments', 'driver_pix_profile_month_id')) {
                    $table->dropConstrainedForeignId('driver_pix_profile_month_id');
                }
            });

            Schema::table('driver_pix_payments', function (Blueprint $table) {
                foreach (['reference_month', 'bus_number'] as $column) {
                    if (Schema::hasColumn('driver_pix_payments', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('driver_pix_profile_months');

        if (Schema::hasTable('driver_pix_profiles')) {
            Schema::table('driver_pix_profiles', function (Blueprint $table) {
                foreach (['cpf', 'last_confirmed_month'] as $column) {
                    if (Schema::hasColumn('driver_pix_profiles', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
