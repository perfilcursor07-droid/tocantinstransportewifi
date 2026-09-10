<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntervalAccessDay extends Model
{
    protected $fillable = ['user_id', 'payment_id', 'access_date', 'started_at', 'expires_at'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
