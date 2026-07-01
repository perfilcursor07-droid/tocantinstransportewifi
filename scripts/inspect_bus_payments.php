<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$from = '2026-05-29 00:00:00';
$to = '2026-06-30 23:59:59';
$serial5021 = 'HH50AB8F056';

$payments = \App\Models\Payment::with('user')
    ->where('status', 'completed')
    ->whereBetween('created_at', [$from, $to])
    ->whereHas('user', fn ($q) => $q->where('last_mikrotik_id', $serial5021))
    ->orderBy('created_at')
    ->get();

echo "Total pagamentos 5021 no periodo: {$payments->count()}\n";
echo 'Receita: R$ ' . number_format($payments->sum('amount'), 2, ',', '.') . "\n\n";

// Users with single payment in period (safest to reassign)
$byUser = $payments->groupBy('user_id');
$singlePaymentUsers = $byUser->filter(fn ($g) => $g->count() === 1);

echo "Usuarios com 1 unico pagamento no periodo (5021): {$singlePaymentUsers->count()}\n\n";

$i = 0;
foreach ($singlePaymentUsers->take(12) as $userId => $group) {
    $p = $group->first();
    $i++;
    echo "#{$i} payment_id={$p->id} user_id={$userId} amount={$p->amount} at={$p->created_at}\n";
}

// Current report totals
echo "\n--- Relatorio atual por onibus ---\n";
$busNames = \App\Models\Bus::getSerialNameMap();
$data = \App\Models\Payment::where('payments.status', 'completed')
    ->whereBetween('payments.created_at', [$from, $to])
    ->join('users', 'payments.user_id', '=', 'users.id')
    ->selectRaw("COALESCE(users.last_mikrotik_id, 'desconhecido') as bus_id")
    ->selectRaw('SUM(payments.amount) as total')
    ->selectRaw('COUNT(payments.id) as count')
    ->groupBy('bus_id')
    ->orderByDesc('total')
    ->get();

foreach ($data as $row) {
    $name = $busNames[$row->bus_id] ?? $row->bus_id;
    if (in_array($name, ['5021', '3099', '3097']) || in_array($row->bus_id, ['HH50AB8F056', 'HH50A7TMT8M', 'HH50A914NK5'])) {
        echo "{$name} ({$row->bus_id}): {$row->count} pagamentos, R$ " . number_format($row->total, 2, ',', '.') . "\n";
    }
}
