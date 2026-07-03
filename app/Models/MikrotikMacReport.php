<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class MikrotikMacReport extends Model
{
    protected $fillable = [
        'ip_address',
        'mac_address',
        'transaction_id',
        'mikrotik_ip',
        'mikrotik_id',
        'reported_at',
        'last_seen',
    ];

    protected function casts(): array
    {
        return [
            'reported_at' => 'datetime',
        ];
    }

    /**
     * Scope para buscar reports recentes (última hora)
     */
    public function scopeRecent($query)
    {
        return $query->where('reported_at', '>=', Carbon::now()->subHour());
    }

    /**
     * Buscar MAC mais recente para um IP
     * @param string $ipAddress
     * @param string|null $mikrotikId Serial number do MikroTik (filtra por ônibus)
     */
    public static function getLatestMacForIp($ipAddress, $mikrotikId = null)
    {
        $query = static::where('ip_address', $ipAddress)
            ->recent()
            ->orderBy('reported_at', 'desc');

        // Se mikrotik_id fornecido, priorizar reports daquele MikroTik
        // (evita colisão de IP entre ônibus com mesma faixa 10.5.50.x)
        if ($mikrotikId) {
            $query->where('mikrotik_id', $mikrotikId);
        }

        return $query->first();
    }

    /**
     * Limpar reports antigos (mais de 1 hora)
     */
    public static function cleanOldReports()
    {
        return static::where('reported_at', '<', Carbon::now()->subHour())->delete();
    }

    /**
     * Resolve o serial do MikroTik (ônibus) a partir do MAC ou IP interno do hotspot.
     * Fonte confiável — cada report vem do script registrarMacs com parâmetro mid.
     */
    public static function resolveMikrotikIdForDevice(?string $macAddress, ?string $internalIp = null): ?string
    {
        if ($macAddress) {
            $normalized = strtoupper(str_replace('-', ':', trim($macAddress)));

            $report = static::where('mac_address', $normalized)
                ->whereNotNull('mikrotik_id')
                ->where('mikrotik_id', '!=', '')
                ->recent()
                ->orderByDesc('reported_at')
                ->first();

            if ($report) {
                return $report->mikrotik_id;
            }
        }

        if ($internalIp && str_starts_with($internalIp, '10.5.50.')) {
            $ids = static::where('ip_address', $internalIp)
                ->whereNotNull('mikrotik_id')
                ->where('mikrotik_id', '!=', '')
                ->recent()
                ->pluck('mikrotik_id')
                ->unique()
                ->filter();

            if ($ids->count() === 1) {
                return $ids->first();
            }
        }

        return null;
    }
}
