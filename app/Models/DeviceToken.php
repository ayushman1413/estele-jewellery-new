<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'push_token',
        'device_token',
        'token',
        'expo_push_token',
        'device_id',
        'platform',
        'provider',
        'app_version',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Keep push_token, device_token, token, and expo_push_token synchronized seamlessly.
     */
    protected function pushToken(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ?: ($this->attributes['device_token'] ?? $this->attributes['token'] ?? $this->attributes['expo_push_token'] ?? null),
            set: function ($value) {
                return [
                    'push_token' => $value,
                    'device_token' => $this->attributes['device_token'] ?? $value,
                    'token' => $this->attributes['token'] ?? $value,
                    'expo_push_token' => $this->attributes['expo_push_token'] ?? $value,
                ];
            }
        );
    }

    protected function deviceToken(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ?: ($this->attributes['push_token'] ?? $this->attributes['token'] ?? $this->attributes['expo_push_token'] ?? null),
            set: function ($value) {
                return [
                    'device_token' => $value,
                    'push_token' => $this->attributes['push_token'] ?? $value,
                    'token' => $this->attributes['token'] ?? $value,
                    'expo_push_token' => $this->attributes['expo_push_token'] ?? $value,
                ];
            }
        );
    }

    protected function token(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ?: ($this->attributes['push_token'] ?? $this->attributes['device_token'] ?? $this->attributes['expo_push_token'] ?? null),
            set: function ($value) {
                return [
                    'token' => $value,
                    'push_token' => $this->attributes['push_token'] ?? $value,
                    'device_token' => $this->attributes['device_token'] ?? $value,
                    'expo_push_token' => $this->attributes['expo_push_token'] ?? $value,
                ];
            }
        );
    }

    protected function expoPushToken(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ?: ($this->attributes['push_token'] ?? $this->attributes['device_token'] ?? $this->attributes['token'] ?? null),
            set: function ($value) {
                return [
                    'expo_push_token' => $value,
                    'push_token' => $this->attributes['push_token'] ?? $value,
                    'device_token' => $this->attributes['device_token'] ?? $value,
                    'token' => $this->attributes['token'] ?? $value,
                ];
            }
        );
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForGuest($query)
    {
        return $query->whereNull('user_id');
    }

    public function scopeForDevice($query, string $deviceId)
    {
        return $query->where('device_id', $deviceId);
    }
}
