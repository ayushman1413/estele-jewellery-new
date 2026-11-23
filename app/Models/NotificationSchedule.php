<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class NotificationSchedule extends Model
{
    use HasFactory;
    protected static function booted(): void
    {
        static::creating(function (NotificationSchedule $schedule) {
            if (empty($schedule->notification_type)) {
                $schedule->notification_type = 'lifecycle_promo';
            }
        });
    }


    public const SEGMENT_GUEST = 'guest';
    public const SEGMENT_REGISTERED = 'registered';
    public const SEGMENT_PURCHASED = 'purchased';

    public const INTERVAL_GUEST_DAYS = 7;
    public const INTERVAL_REGISTERED_DAYS = 30;
    public const INTERVAL_PURCHASED_DAYS = 90;

    protected $fillable = [
        'user_id',
        'device_id',
        'device_token_id',
        'segment',
        'notification_type',
        'last_sent_at',
        'next_send_at',
        'last_notified_at',
        'next_eligible_at',
        'cycle_count',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'last_sent_at' => 'datetime',
            'next_send_at' => 'datetime',
            'last_notified_at' => 'datetime',
            'next_eligible_at' => 'datetime',
            'cycle_count' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function nextSendAt(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                $raw = $value ?: ($this->attributes['next_eligible_at'] ?? null);
                return $raw ? Carbon::parse($raw) : null;
            },
            set: fn ($value) => [
                'next_send_at' => $value,
                'next_eligible_at' => $value,
            ]
        );
    }

    protected function nextEligibleAt(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                $raw = $value ?: ($this->attributes['next_send_at'] ?? null);
                return $raw ? Carbon::parse($raw) : null;
            },
            set: fn ($value) => [
                'next_eligible_at' => $value,
                'next_send_at' => $value,
            ]
        );
    }

    protected function lastSentAt(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                $raw = $value ?: ($this->attributes['last_notified_at'] ?? null);
                return $raw ? Carbon::parse($raw) : null;
            },
            set: fn ($value) => [
                'last_sent_at' => $value,
                'last_notified_at' => $value,
            ]
        );
    }

    protected function lastNotifiedAt(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                $raw = $value ?: ($this->attributes['last_sent_at'] ?? null);
                return $raw ? Carbon::parse($raw) : null;
            },
            set: fn ($value) => [
                'last_notified_at' => $value,
                'last_sent_at' => $value,
            ]
        );
    }

    protected function deviceTokenId(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ?: ($this->attributes['device_id'] ?? null),
            set: fn ($value) => [
                'device_token_id' => $value,
                'device_id' => $this->attributes['device_id'] ?? $value,
            ]
        );
    }

    public function scopeDueForNotification($query)
    {
        return $query->where(function ($q) {
            $q->where(function ($q2) {
                $q2->whereNotNull('next_eligible_at')
                   ->where('next_eligible_at', '<=', now());
            })->orWhere(function ($q2) {
                $q2->whereNotNull('next_send_at')
                   ->where('next_send_at', '<=', now());
            });
        });
    }

    public function scopeForSegment($query, string $segment)
    {
        return $query->where('segment', $segment);
    }
}
