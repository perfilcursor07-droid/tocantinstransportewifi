<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusHealthLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'bus_id',
        'status',
        'seconds_since_sync',
        'public_ip',
        'active_users',
        'latency_ms',
        'recorded_at',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
    ];

    public function bus(): BelongsTo
    {
        return $this->belongsTo(Bus::class);
    }
}
