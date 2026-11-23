<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralUsage extends Model
{
    protected $fillable = [
        'referrer_id',
        'referee_id',
        'order_id',
        'referrer_credit',
        'referee_credit',
    ];

    protected function casts(): array
    {
        return [
            'referrer_credit' => 'decimal:2',
            'referee_credit' => 'decimal:2',
        ];
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    public function referee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referee_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
