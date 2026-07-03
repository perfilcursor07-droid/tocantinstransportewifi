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
}
