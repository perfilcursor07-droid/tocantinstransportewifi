<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverPixProfile extends Model
{
    protected $fillable = [
        'registration_link_id',
        'full_name',
        'phone',
        'pix_key',
        'pix_key_type',
        'bus_number',
        'status',
        'rejected_reason',
        'admin_notes',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
        ];
    }

    public function registrationLink()
    {
        return $this->belongsTo(DriverPixRegistrationLink::class, 'registration_link_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function payments()
    {
        return $this->hasMany(DriverPixPayment::class);
    }

    public function latestPendingPayment()
    {
        return $this->hasOne(DriverPixPayment::class)->ofMany(
            ['created_at' => 'max'],
            fn ($query) => $query->where('status', 'pending')
        );
    }

    public function latestPaidPayment()
    {
        return $this->hasOne(DriverPixPayment::class)->ofMany(
            ['paid_at' => 'max'],
            fn ($query) => $query->where('status', 'paid')
        );
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function maskedPixKey(): string
    {
        $key = $this->pix_key;
        if ($this->pix_key_type === 'email') {
            $parts = explode('@', $key);
            if (count($parts) === 2) {
                $local = $parts[0];
                $masked = strlen($local) > 2
                    ? substr($local, 0, 2) . str_repeat('*', max(1, strlen($local) - 2)) . '@' . $parts[1]
                    : $key;

                return $masked;
            }
        }

        if (strlen($key) <= 6) {
            return $key;
        }

        return substr($key, 0, 3) . str_repeat('*', strlen($key) - 6) . substr($key, -3);
    }

    public static function detectPixKeyType(string $key, ?string $phoneHint = null): string
    {
        $normalized = trim($key);

        if (filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            return 'email';
        }

        $digits = preg_replace('/\D/', '', $normalized);
        $phoneDigits = $phoneHint ? preg_replace('/\D/', '', $phoneHint) : null;

        if ($phoneDigits && $digits === $phoneDigits) {
            return 'phone';
        }

        if (strlen($digits) === 11) {
            if (self::isValidCpf($digits)) {
                return 'cpf';
            }

            if (self::looksLikeMobilePhone($digits)) {
                return 'phone';
            }

            return 'cpf';
        }

        if (strlen($digits) === 14) {
            return 'cnpj';
        }

        if (strlen($digits) >= 10 && strlen($digits) <= 13) {
            return 'phone';
        }

        return 'random';
    }

    public static function isValidCpf(string $cpf): bool
    {
        $cpf = preg_replace('/\D/', '', $cpf);

        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $sum = 0;
            for ($i = 0; $i < $t; $i++) {
                $sum += (int) $cpf[$i] * (($t + 1) - $i);
            }
            $digit = ((10 * $sum) % 11) % 10;
            if ((int) $cpf[$t] !== $digit) {
                return false;
            }
        }

        return true;
    }

    public static function looksLikeMobilePhone(string $digits): bool
    {
        if (strlen($digits) !== 11) {
            return false;
        }

        $ddd = (int) substr($digits, 0, 2);

        return $ddd >= 11 && $ddd <= 99 && $digits[2] === '9';
    }

    public function effectivePixKeyType(): string
    {
        $pixDigits = preg_replace('/\D/', '', $this->pix_key);
        $phoneDigits = preg_replace('/\D/', '', (string) $this->phone);

        if ($phoneDigits !== '' && $pixDigits === $phoneDigits) {
            return 'phone';
        }

        if ($this->pix_key_type === 'phone') {
            return 'phone';
        }

        if (strlen($pixDigits) === 11 && ! self::isValidCpf($pixDigits) && self::looksLikeMobilePhone($pixDigits)) {
            return 'phone';
        }

        return $this->pix_key_type ?: self::detectPixKeyType($this->pix_key, $this->phone);
    }

    public static function normalizePixKey(string $key, string $type): string
    {
        $normalized = trim($key);

        if (in_array($type, ['cpf', 'cnpj', 'phone'], true)) {
            return preg_replace('/\D/', '', $normalized);
        }

        if ($type === 'email') {
            return strtolower($normalized);
        }

        return $normalized;
    }

    public function formattedPhone(): string
    {
        if (! $this->phone) {
            return '—';
        }

        $digits = preg_replace('/\D/', '', $this->phone);

        if (strlen($digits) === 11) {
            return '(' . substr($digits, 0, 2) . ') ' . substr($digits, 2, 5) . '-' . substr($digits, 7);
        }

        if (strlen($digits) === 10) {
            return '(' . substr($digits, 0, 2) . ') ' . substr($digits, 2, 4) . '-' . substr($digits, 6);
        }

        return $this->phone;
    }
}
