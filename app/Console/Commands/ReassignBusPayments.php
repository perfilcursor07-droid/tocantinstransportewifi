<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Redistribui pagamentos entre ônibus no relatório.
 *
 * O relatório "Receita por Ônibus" usa users.last_mikrotik_id (serial MikroTik),
 * NÃO um campo histórico no pagamento. Alterar last_mikrotik_id do usuário
 * move todos os pagamentos dele no período para o novo ônibus.
 *
 * Uso:
 *   php artisan reports:reassign-bus-payments --dry-run
 *   php artisan reports:reassign-bus-payments --execute
 */
class ReassignBusPayments extends Command
{
    protected $signature = 'reports:reassign-bus-payments
                            {--from=HH50AB8F056 : Serial MikroTik de origem (5021)}
                            {--to3099=HH50A7TMT8M : Serial destino 3099}
                            {--to3097=HH50A914NK5 : Serial destino 3097}
                            {--count3099=5 : Quantidade para 3099}
                            {--count3097=5 : Quantidade para 3097}
                            {--start=2026-05-29 00:00:00 : Início do período}
                            {--end=2026-06-30 23:59:59 : Fim do período}
                            {--dry-run : Apenas mostra o que seria alterado}
                            {--execute : Aplica as alterações}';

    protected $description = 'Redistribui pagamentos entre ônibus alterando last_mikrotik_id dos usuários';

    public function handle(): int
    {
        $from = $this->option('from');
        $to3099 = $this->option('to3099');
        $to3097 = $this->option('to3097');
        $count3099 = (int) $this->option('count3099');
        $count3097 = (int) $this->option('count3097');
        $start = $this->option('start');
        $end = $this->option('end');
        $dryRun = $this->option('dry-run');
        $execute = $this->option('execute');

        if (! $dryRun && ! $execute) {
            $this->warn('Use --dry-run para simular ou --execute para aplicar.');

            return self::FAILURE;
        }

        $this->info("Período: {$start} → {$end}");
        $this->info("Origem: {$from} (5021) → {$count3099}x {$to3099} (3099) + {$count3097}x {$to3097} (3097)");
        $this->newLine();

        // Preferir usuários com 1 único pagamento no período (evita mover pagamentos extras)
        $candidates = Payment::query()
            ->where('status', 'completed')
            ->whereBetween('created_at', [$start, $end])
            ->whereHas('user', fn ($q) => $q->where('last_mikrotik_id', $from))
            ->select('user_id')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) = 1')
            ->orderByRaw('MIN(created_at)')
            ->pluck('user_id');

        $needed = $count3099 + $count3097;

        if ($candidates->count() < $needed) {
            $this->error("Só encontrados {$candidates->count()} usuários com 1 pagamento no 5021. Precisa de {$needed}.");

            return self::FAILURE;
        }

        $for3099 = $candidates->take($count3099);
        $for3097 = $candidates->slice($count3099, $count3097);

        $this->table(
            ['Destino', 'User ID', 'Payment ID', 'Valor', 'Data'],
            $this->buildPreviewRows($for3099, $to3099, '3099', $start, $end)
                ->merge($this->buildPreviewRows($for3097, $to3097, '3097', $start, $end))
                ->all()
        );

        $this->newLine();
        $this->showBusTotals($start, $end, $from, $to3099, $to3097, 'ANTES');

        if ($dryRun) {
            $this->newLine();
            $this->comment('Simulação concluída. Rode com --execute para aplicar.');

            return self::SUCCESS;
        }

        if (! $this->confirm('Confirma a redistribuição acima?')) {
            $this->warn('Cancelado.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($for3099, $for3097, $to3099, $to3097) {
            User::whereIn('id', $for3099)->update(['last_mikrotik_id' => $to3099]);
            User::whereIn('id', $for3097)->update(['last_mikrotik_id' => $to3097]);
        });

        $this->info('✅ Redistribuição aplicada.');
        $this->newLine();
        $this->showBusTotals($start, $end, $from, $to3099, $to3097, 'DEPOIS');

        return self::SUCCESS;
    }

    private function buildPreviewRows($userIds, string $serial, string $label, string $start, string $end)
    {
        return collect($userIds)->map(function ($userId) use ($serial, $label, $start, $end) {
            $payment = Payment::where('user_id', $userId)
                ->where('status', 'completed')
                ->whereBetween('created_at', [$start, $end])
                ->first();

            return [
                $label,
                $userId,
                $payment?->id ?? '-',
                $payment ? 'R$ '.number_format($payment->amount, 2, ',', '.') : '-',
                $payment?->created_at?->format('d/m/Y H:i') ?? '-',
            ];
        });
    }

    private function showBusTotals(string $start, string $end, string $from, string $to3099, string $to3097, string $label): void
    {
        $serials = [$from, $to3099, $to3097];
        $names = ['5021', '3099', '3097'];

        $this->info("--- Totais {$label} ---");

        foreach ($serials as $i => $serial) {
            $row = Payment::where('payments.status', 'completed')
                ->whereBetween('payments.created_at', [$start, $end])
                ->join('users', 'payments.user_id', '=', 'users.id')
                ->where('users.last_mikrotik_id', $serial)
                ->selectRaw('COUNT(payments.id) as cnt, SUM(payments.amount) as total')
                ->first();

            $this->line(sprintf(
                '%s (%s): %d pagamentos, R$ %s',
                $names[$i],
                $serial,
                $row->cnt ?? 0,
                number_format($row->total ?? 0, 2, ',', '.')
            ));
        }
    }
}
