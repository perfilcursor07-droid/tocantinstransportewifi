<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverPixPayment extends Model
{
    protected $fillable = [
        'driver_pix_profile_id',
        'amount',
        'description',
        'status',
        'payment_reference',
        'notes',
        'paid_by',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function profile()
    {
        return $this->belongsTo(DriverPixProfile::class, 'driver_pix_profile_id');
    }

    public function payer()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}
