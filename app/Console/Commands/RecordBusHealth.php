<?php

namespace App\Console\Commands;

use App\Models\Bus;
use App\Models\BusHealthLog;
use App\Models\User;
use Illuminate\Console\Command;

class RecordBusHealth extends Command
{
    protected $signature = 'bus:record-health';
    protected $description = 'Grava snapshot de saúde de cada MikroTik (roda a cada 5min)';

    public function handle(): int
    {
        $buses = Bus::all();

        foreach ($buses as $bus) {
            $seconds = $bus->last_sync_at
                ? (int) $bus->last_sync_at->diffInSeconds(now())
                : null;

            if ($seconds === null) {
                $status = 'unknown';
            } elseif ($seconds <= 30) {
                $status = 'online';
            } elseif ($seconds <= 300) {
                $status = 'lagging';
            } else {
                $status = 'offline';
            }

            $activeUsers = User::where('last_mikrotik_id', $bus->mikrotik_serial)
                ->whereIn('status', ['connected', 'active', 'temp_bypass'])
                ->where('expires_at', '>', now())
                ->count();

            // Medir latência via TCP ping pro IP público do MikroTik (porta 80 ou 8291)
            $latencyMs = null;
            if ($bus->last_public_ip && in_array($status, ['online', 'lagging'])) {
                $latencyMs = $this->measureLatency($bus->last_public_ip);
            }

            BusHealthLog::create([
                'bus_id' => $bus->id,
                'status' => $status,
                'seconds_since_sync' => $seconds,
                'public_ip' => $bus->last_public_ip,
                'active_users' => $activeUsers,
                'latency_ms' => $latencyMs,
                'recorded_at' => now(),
            ]);
        }

        // Limpar logs com mais de 30 dias
        BusHealthLog::where('recorded_at', '<', now()->subDays(30))->delete();

        $this->info("Saúde gravada para {$buses->count()} MikroTiks.");

        return self::SUCCESS;
    }

    /**
     * Mede latência via TCP connect ao IP do MikroTik.
     * Tenta porta 8291 (Winbox), fallback porta 80.
     * Retorna latência em ms ou null se falhar.
     */
    private function measureLatency(string $ip): ?int
    {
        // Tentar porta 8291 (Winbox - sempre aberta num MikroTik)
        $latency = $this->tcpPing($ip, 8291, 3);

        if ($latency === null) {
            // Fallback: porta 80
            $latency = $this->tcpPing($ip, 80, 3);
        }

        return $latency;
    }

    /**
     * TCP connect ping: mede o tempo para abrir uma conexão TCP.
     * Timeout em segundos.
     */
    private function tcpPing(string $ip, int $port, int $timeout): ?int
    {
        $start = hrtime(true);
        $socket = @fsockopen($ip, $port, $errno, $errstr, $timeout);

        if ($socket) {
            $elapsed = (hrtime(true) - $start) / 1_000_000; // nanosegundos → ms
            fclose($socket);
            return (int) round($elapsed);
        }

        return null;
    }
}
