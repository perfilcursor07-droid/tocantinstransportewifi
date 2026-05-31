<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceTicket extends Model
{
    protected $fillable = [
        'title',
        'description',
        'mikrotik_id',
        'bus_number',
        'scheduled_date',
        'status',
        'resolution',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'closed_at' => 'datetime',
        ];
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }

    public static function openCount(): int
    {
        return static::where('status', 'open')->count();
    }

    /**
     * Mapeamento serial → número do carro
     */
    public static function busMap(): array
    {
        return [
            'HH50A914NK5' => '3097',
            'HH50A7TMT8M' => '3099',
            'HH60A2NSBE7' => '5013',
            'HH50AB8F056' => '5021',
            'HGD09YS6037' => '5023',
            'HGK09Q76FMP' => '5031',
            'HH50A2ER2JB' => '5033',
            'HGJ09X2F8FD' => '5035',
        ];
    }

    public function getBusDisplayAttribute(): string
    {
        if ($this->bus_number && $this->mikrotik_id) {
            return "{$this->bus_number} - {$this->mikrotik_id}";
        }
        return $this->bus_number ?: $this->mikrotik_id ?: 'Não especificado';
    }
}
