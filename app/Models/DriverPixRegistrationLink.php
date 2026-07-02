<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DriverPixRegistrationLink extends Model
{
    protected $fillable = [
        'token',
        'label',
        'is_active',
        'expires_at',
        'uses_count',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
            'uses_count' => 'integer',
        ];
    }

    public static function generateToken(): string
    {
        do {
            $token = Str::random(32);
        } while (self::where('token', $token)->exists());

        return $token;
    }

    public function isUsable(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function publicUrl(): string
    {
        return route('driver-pix.register', $this->token);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function profiles()
    {
        return $this->hasMany(DriverPixProfile::class, 'registration_link_id');
    }
}
