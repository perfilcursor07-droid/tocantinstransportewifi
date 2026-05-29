<?php

namespace App\Console\Commands;

use App\Models\Bus;
use App\Models\BusHealthLog;
use App\Models\User;
use App\Services\NtfyService;
use Illuminate\Console\Command;

class RecordBusHealth extends Command
{
    protected $signature = 'bus:record-health';
    protected $description = 'Grava snapshot de saúde de cada MikroTik (roda a cada 5min)';

    public function handle(NtfyService $ntfy): int
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

            // Medir latência via TCP ping pro IP público do MikroTik
            $latencyMs = null;
            if ($bus->last_public_ip && in_array($status, ['online', 'lagging'])) {
                $latencyMs = $this->measureLatency($bus->last_public_ip);
            }

            // Buscar o status anterior para detectar transição
            $previousLog = BusHealthLog::where('bus_id', $bus->id)
                ->orderByDesc('recorded_at')
                ->first();

            $previousStatus = $previousLog?->status;
            $wasOnline = in_array($previousStatus, ['online', 'lagging']);
            $isOnline = in_array($status, ['online', 'lagging']);

            // Gravar snapshot
            BusHealthLog::create([
                'bus_id' => $bus->id,
                'status' => $status,
                'seconds_since_sync' => $seconds,
                'public_ip' => $bus->last_public_ip,
                'active_users' => $activeUsers,
                'latency_ms' => $latencyMs,
                'recorded_at' => now(),
            ]);

            // === NOTIFICAÇÕES NTFY (tópico Starlink) ===

            $location = trim(($bus->last_city ?? '') . ' ' . ($bus->last_state ?? '')) ?: null;

            // 1. Transição: estava online → ficou offline
            if ($wasOnline && !$isOnline) {
                $ntfy->notifyBusOffline(
                    $bus->name,
                    $bus->mikrotik_serial,
                    $bus->last_public_ip,
                    $location
                );
                $this->warn("  🔴 {$bus->name} ficou OFFLINE → notificação enviada");
            }

            // 2. Transição: estava offline → voltou online
            if (!$wasOnline && $isOnline && $previousStatus !== null) {
                // Calcular quanto tempo ficou offline (contar checks offline seguidos × 5min)
                $offlineMinutes = $this->calculateOfflineDuration($bus->id);

                $ntfy->notifyBusOnline(
                    $bus->name,
                    $bus->mikrotik_serial,
                    $offlineMinutes,
                    $latencyMs
                );
                $this->info("  🟢 {$bus->name} voltou ONLINE (offline por {$offlineMinutes}min) → notificação enviada");
            }

            // 3. Internet lenta (>500ms) — só notifica se estava OK antes (evitar spam)
            if ($isOnline && $latencyMs !== null && $latencyMs > 500) {
                $previousLatency = $previousLog?->latency_ms;
                // Só notifica se a latência anterior era OK (evitar repetir a cada 5min)
                if ($previousLatency === null || $previousLatency <= 500) {
                    $ntfy->notifyBusSlow($bus->name, $bus->mikrotik_serial, $latencyMs);
                    $this->warn("  🟡 {$bus->name} LENTA ({$latencyMs}ms) → notificação enviada");
                }
            }
        }

        // Limpar logs com mais de 30 dias
        BusHealthLog::where('recorded_at', '<', now()->subDays(30))->delete();

        $this->info("Saúde gravada para {$buses->count()} MikroTiks.");

        return self::SUCCESS;
    }

    /**
     * Calcula quantos minutos o ônibus ficou offline (checks offline consecutivos × 5min).
     */
    private function calculateOfflineDuration(int $busId): int
    {
        // Pegar os últimos logs em ordem reversa até encontrar um online
        $recentLogs = BusHealthLog::where('bus_id', $busId)
            ->orderByDesc('recorded_at')
            ->take(300) // max ~25h de histórico
            ->get();

        $offlineChecks = 0;
        foreach ($recentLogs as $log) {
            if (in_array($log->status, ['online', 'lagging'])) {
                break; // Encontrou quando estava online pela última vez
            }
            $offlineChecks++;
        }

        // Cada check = 5 minutos. Mínimo = 5min (acabou de voltar)
        return max($offlineChecks * 5, 5);
    }

    /**
     * Mede latência via TCP connect ao IP do MikroTik.
     * Tenta porta 8291 (Winbox), fallback porta 80.
     */
    private function measureLatency(string $ip): ?int
    {
        $latency = $this->tcpPing($ip, 8291, 3);

        if ($latency === null) {
            $latency = $this->tcpPing($ip, 80, 3);
        }

        return $latency;
    }

    /**
     * TCP connect ping: mede o tempo para abrir uma conexão TCP.
     */
    private function tcpPing(string $ip, int $port, int $timeout): ?int
    {
        $start = hrtime(true);
        $socket = @fsockopen($ip, $port, $errno, $errstr, $timeout);

        if ($socket) {
            $elapsed = (hrtime(true) - $start) / 1_000_000; // ns → ms
            fclose($socket);
            return (int) round($elapsed);
        }

        return null;
    }
}
