<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'user_id',
        'amount',
        'payment_type',
        'status',
        'payment_id',
        'transaction_id',
        'pix_emv_string',
        'pix_location',
        'gateway_payment_id',
        'payment_data',
        'paid_at',
        'unpaid_reminder_sent_at',
        'whatsapp_confirmation_sent_at',
        'refund_receipt_path',
        'refunded_at',
        'refund_note',
    ];

    protected function casts(): array
    {
        return [
            'payment_data' => 'array',
            'paid_at' => 'datetime',
            'unpaid_reminder_sent_at' => 'datetime',
            'whatsapp_confirmation_sent_at' => 'datetime',
            'refunded_at' => 'datetime',
            'amount' => 'decimal:2',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sessions()
    {
        return $this->hasMany(Session::class);
    }

    public function isRefunded(): bool
    {
        return $this->status === 'refunded';
    }

    public function hasRefundReceipt(): bool
    {
        return filled($this->refund_receipt_path);
    }
}
