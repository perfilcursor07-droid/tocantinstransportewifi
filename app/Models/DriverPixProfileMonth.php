<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class DriverPixProfileMonth extends Model
{
    protected $fillable = [
        'driver_pix_profile_id',
        'reference_month',
        'bus_number',
        'pix_key',
        'pix_key_type',
        'status',
        'is_update',
        'changed_fields',
        'source',
        'registration_link_id',
        'submitted_at',
        'rejected_reason',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'reference_month' => 'date',
            'is_update' => 'boolean',
            'changed_fields' => 'array',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public const MONTH_NAMES = [
        1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
        5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
        9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
    ];

    public function profile()
    {
        return $this->belongsTo(DriverPixProfile::class, 'driver_pix_profile_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function registrationLink()
    {
        return $this->belongsTo(DriverPixRegistrationLink::class, 'registration_link_id');
    }

    public function payments()
    {
        return $this->hasMany(DriverPixPayment::class, 'driver_pix_profile_month_id');
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function monthLabel(): string
    {
        return self::labelFor($this->reference_month);
    }

    public function shortMonthLabel(): string
    {
        return self::shortLabelFor($this->reference_month);
    }

    /* ------------------------------------------------------------------ */
    /* Helpers de competência (mês de referência)                          */
    /* ------------------------------------------------------------------ */

    /** Normaliza "2026-08" ou "2026-08-17" para o primeiro dia do mês. */
    public static function normalizeMonth(?string $value): Carbon
    {
        if (! $value) {
            return now()->startOfMonth();
        }

        $value = trim($value);

        if (preg_match('/^(\d{4})-(\d{1,2})$/', $value, $m)) {
            return Carbon::create((int) $m[1], (int) $m[2], 1)->startOfMonth();
        }

        try {
            return Carbon::parse($value)->startOfMonth();
        } catch (\Throwable) {
            return now()->startOfMonth();
        }
    }

    public static function isValidMonthInput(?string $value): bool
    {
        return is_string($value) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', trim($value)) === 1;
    }

    public static function labelFor($date): string
    {
        $date = $date instanceof Carbon ? $date : Carbon::parse($date);

        return self::MONTH_NAMES[$date->month] . ' de ' . $date->year;
    }

    public static function shortLabelFor($date): string
    {
        $date = $date instanceof Carbon ? $date : Carbon::parse($date);

        return mb_substr(self::MONTH_NAMES[$date->month], 0, 3) . '/' . $date->format('y');
    }

    /**
     * Meses disponíveis para seleção: do mês atual para trás.
     *
     * @return array<string, string> ['2026-08' => 'Agosto de 2026', ...]
     */
    public static function monthOptions(int $past = 11, int $future = 0): array
    {
        $options = [];
        $cursor = now()->startOfMonth()->addMonths($future);

        for ($i = 0; $i <= $past + $future; $i++) {
            $options[$cursor->format('Y-m')] = self::labelFor($cursor);
            $cursor = $cursor->copy()->subMonth();
        }

        return $options;
    }
}
