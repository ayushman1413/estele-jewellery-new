<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class OldJewelleryRequest extends Model implements HasMedia
{
    use InteractsWithMedia;

    /**
     * Mirrors Order::ALLOWED_TRANSITIONS — enforced in updating() below so no
     * direct API/tinker/bulk update can skip or reverse the pipeline.
     */
    public const ALLOWED_TRANSITIONS = [
        'pending' => ['submitted', 'cancelled'],
        // 'bidding_active' is reached directly from 'submitted' in the real
        // flow — VendorInvitationService::inviteAll() notifies vendors and
        // opens the bidding window in the same step, so there is no
        // meaningful intermediate state between them. 'vendors_notified'
        // stays a valid status value (referenced by existing test fixtures
        // and still a legal target here) but inviteAll() no longer produces
        // it as of the fix for the dead-letter bidding-close bug.
        'submitted' => ['vendors_notified', 'bidding_active', 'cancelled'],
        'vendors_notified' => ['bidding_active', 'cancelled'],
        'bidding_active' => ['bidding_closed', 'cancelled'],
        'bidding_closed' => ['bid_selected', 'cancelled'],
        'bid_selected' => ['wallet_pending', 'cancelled'],
        'wallet_pending' => ['wallet_credited', 'cancelled'],
        'wallet_credited' => ['wallet_expired', 'completed'],
        'wallet_expired' => ['completed'],
        'completed' => [],
        'cancelled' => [],
    ];

    protected $fillable = [
        'user_id',
        'request_number',
        'description',
        'status',
        'bidding_start_at',
        'bidding_end_at',
        'winning_bid_id',
        'final_amount',
        'deduction_amount',
        'credited_amount',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'bidding_start_at' => 'datetime',
            'bidding_end_at' => 'datetime',
            'final_amount' => 'decimal:2',
            'deduction_amount' => 'decimal:2',
            'credited_amount' => 'decimal:2',
            'closed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'request_number';
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')
            ->useDisk('original_images')
            ->storeConversionsOnDisk('public')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->singleFile();

        $this->addMediaCollection('video')
            ->useDisk('original_images')
            ->acceptsMimeTypes(['video/mp4', 'video/quicktime'])
            ->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->performOnCollections('image')
            ->width(400)
            ->format('png')
            ->quality(78);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(OldJewelleryVendorInvitation::class);
    }

    public function bids(): HasMany
    {
        return $this->hasMany(OldJewelleryBid::class);
    }

    public function winningBid(): BelongsTo
    {
        return $this->belongsTo(OldJewelleryBid::class, 'winning_bid_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(OldJewelleryActivityLog::class);
    }

    public function walletCredit(): HasOne
    {
        return $this->hasOne(OldJewelleryWalletCredit::class);
    }

    protected static function booted(): void
    {
        static::updating(function (OldJewelleryRequest $request) {
            if (! $request->isDirty('status')) {
                return;
            }

            $from = $request->getOriginal('status');
            $to = $request->status;

            if (! array_key_exists($from, self::ALLOWED_TRANSITIONS)) {
                return;
            }

            if (! in_array($to, self::ALLOWED_TRANSITIONS[$from], true)) {
                throw ValidationException::withMessages([
                    'status' => "Old jewellery request cannot move from \"{$from}\" to \"{$to}\".",
                ]);
            }
        });

        static::created(function (OldJewelleryRequest $request) {
            if ($request->user) {
                try {
                    $request->user->notify(new \App\Notifications\SellJewelleryUpdateNotification($request, 'submitted'));
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        });

        static::updated(function (OldJewelleryRequest $request) {
            if (! $request->wasChanged('status') || ! $request->user_id) {
                return;
            }

            $status = $request->status;
            $user = $request->user;

            try {
                $event = match ($status) {
                    'submitted' => 'submitted',
                    'bidding_active' => 'bidding_started',
                    'bidding_closed' => 'bidding_closed',
                    'bid_selected', 'approved' => 'approved',
                    'cancelled', 'rejected' => 'rejected',
                    'pending' => 'pending',
                    'wallet_credited', 'completed', 'sold' => 'sold',
                    default => null,
                };

                if ($event) {
                    $user->notify(new \App\Notifications\SellJewelleryUpdateNotification(
                        $request,
                        $event,
                        finalAmount: (float) $request->final_amount,
                        creditedAmount: (float) $request->credited_amount
                    ));
                }

                if ($status === 'bidding_active') {
                    $user->notify(new \App\Notifications\BiddingUpdateNotification($request, 'started'));
                } elseif ($status === 'bidding_closed') {
                    $user->notify(new \App\Notifications\BiddingUpdateNotification($request, 'closed'));
                } elseif ($status === 'bid_selected') {
                    $dedupeKey = "bidding_outcome_notified_{$request->id}_bid_accepted";
                    if (! \Illuminate\Support\Facades\Cache::has($dedupeKey)) {
                        \Illuminate\Support\Facades\Cache::put($dedupeKey, true, 60);
                        $user->notify(new \App\Notifications\BiddingUpdateNotification($request, 'bid_accepted', amount: (float) $request->final_amount));
                    }
                } elseif ($status === 'cancelled' && in_array($request->getOriginal('status'), ['bidding_active', 'bidding_closed', 'bid_selected'], true)) {
                    $dedupeKey = "bidding_outcome_notified_{$request->id}_bid_rejected";
                    if (! \Illuminate\Support\Facades\Cache::has($dedupeKey)) {
                        \Illuminate\Support\Facades\Cache::put($dedupeKey, true, 60);
                        $user->notify(new \App\Notifications\BiddingUpdateNotification($request, 'bid_rejected'));
                    }
                }
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }
}
