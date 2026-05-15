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

            BusHealthLog::create([
                'bus_id' => $bus->id,
                'status' => $status,
                'seconds_since_sync' => $seconds,
                'public_ip' => $bus->last_public_ip,
                'active_users' => $activeUsers,
                'recorded_at' => now(),
            ]);
        }

        // Limpar logs com mais de 30 dias
        BusHealthLog::where('recorded_at', '<', now()->subDays(30))->delete();

        $this->info("Saúde gravada para {$buses->count()} MikroTiks.");

        return self::SUCCESS;
    }
}
