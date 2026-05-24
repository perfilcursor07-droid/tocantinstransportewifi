<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class ServiceReview extends Model
{
    protected $fillable = [
        'user_id',
        'whatsapp_message_id',
        'token',
        'phone',
        'lid',
        'batch_date',
        'registration_at',
        'invited_at',
        'whatsapp_status',
        'whatsapp_error_message',
        'bot_state',
        'bot_last_interaction_at',
        'rating',
        'reason',
        'submitted_at',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'batch_date' => 'date',
            'registration_at' => 'datetime',
            'invited_at' => 'datetime',
            'submitted_at' => 'datetime',
            'bot_last_interaction_at' => 'datetime',
            'rating' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function whatsappMessage()
    {
        return $this->belongsTo(WhatsappMessage::class);
    }

    public function scopeAnswered($query)
    {
        return $query->whereNotNull('submitted_at');
    }

    public function scopePendingAnswer($query)
    {
        return $query->whereNull('submitted_at');
    }

    public function markWhatsappSent(?WhatsappMessage $message = null): void
    {
        $this->update([
            'whatsapp_message_id' => $message?->id ?? $this->whatsapp_message_id,
            'whatsapp_status' => 'sent',
            'whatsapp_error_message' => null,
            'invited_at' => now(),
        ]);
    }

    public function markWhatsappFailed(string $errorMessage, ?WhatsappMessage $message = null): void
    {
        $this->update([
            'whatsapp_message_id' => $message?->id ?? $this->whatsapp_message_id,
            'whatsapp_status' => 'failed',
            'whatsapp_error_message' => $errorMessage,
        ]);
    }

    public function markSubmitted(int $rating, ?string $reason, ?string $ipAddress, ?string $userAgent): void
    {
        $this->update([
            'rating' => $rating,
            'reason' => $reason,
            'submitted_at' => now(),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }

    public static function resolveBatchWindow(Carbon|string|null $batchDate = null): array
    {
        $reference = $batchDate instanceof Carbon
            ? $batchDate->copy()->startOfDay()
            : Carbon::parse($batchDate ?? now())->startOfDay();

        return [
            'batch_date' => $reference->toDateString(),
            'start' => $reference->copy()->subDay()->setTime(18, 0, 0),
            'end' => $reference->copy()->setTime(7, 0, 0),
            'dispatch_at' => $reference->copy()->setTime(7, 0, 0),
        ];
    }
}