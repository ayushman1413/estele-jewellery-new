<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'phone', 'password', 'wallet_balance', 'referral_code', 'referred_by_user_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    public function canAccessPanel(Panel $panel): bool
    {
        // An invited contact exists before it has a password — it must not be
        // able to reach the panel until the setup link has actually been used.
        if (blank($this->password)) {
            return false;
        }

        $roleNames = $this->roles()->pluck('name');

        // A login whose ONLY role(s) are Vendor::ACCESS_ROLES has its own
        // dedicated portal at /vendor (see routes/web.php and
        // EnsureVendorAccess) and must never reach the Filament admin panel
        // — a vendor's access is jewellery bidding only, nothing store-wide.
        // Someone who is ALSO real staff (holds another role too) keeps
        // panel access through that other role, unaffected by this.
        return $roleNames->isNotEmpty() && $roleNames->diff(Vendor::ACCESS_ROLES)->isNotEmpty();
    }

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (blank($user->referral_code)) {
                do {
                    $code = strtoupper(Str::random(8));
                } while (static::where('referral_code', $code)->exists());

                $user->referral_code = $code;
            }
        });

        // The FK cascade would drop these rows without firing their model
        // events, leaving every photo and video they own on disk. Deleting
        // through Eloquent lets the media library remove the files too.
        static::deleting(function (User $user) {
            $user->oldJewelleryRequests()->get()->each->delete();
            $user->rewardSubmissions()->get()->each->delete();
            $user->tokens()->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
        });
    }

    /**
     * Create a new API bearer token for this user (mobile app auth).
     */
    public function createApiToken(string $name = 'mobile', array $abilities = ['*'], ?\DateTimeInterface $expiresAt = null): string
    {
        return PersonalAccessToken::issue($this, $name, $abilities, $expiresAt);
    }

    /**
     * Revoke every bearer token this user holds (full logout everywhere).
     */
    public function revokeAllApiTokens(): void
    {
        $this->apiTokens()->delete();
    }

    /**
     * Revoke a single token by id — used by POST /api/logout.
     */
    public function revokeApiToken(int $tokenId): void
    {
        $this->apiTokens()->whereKey($tokenId)->delete();
    }

    /**
     * Mobile bearer tokens. They live in the same personal_access_tokens
     * table Sanctum uses, keyed by the tokenable morph columns.
     */
    public function apiTokens(): MorphMany
    {
        return $this->morphMany(PersonalAccessToken::class, 'tokenable');
    }

    /** The bidding vendor this login belongs to, if any. */
    public function vendor(): HasOne
    {
        return $this->hasOne(Vendor::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class)->latest();
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class)->latest();
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class)->latest();
    }

    public function rewardSubmissions(): HasMany
    {
        return $this->hasMany(RewardSubmission::class)->latest();
    }

    public function oldJewelleryRequests(): HasMany
    {
        return $this->hasMany(OldJewelleryRequest::class)->latest();
    }

    public function oldJewelleryWalletCredits(): HasMany
    {
        return $this->hasMany(OldJewelleryWalletCredit::class);
    }

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    public function notificationSchedule(): HasOne
    {
        return $this->hasOne(NotificationSchedule::class);
    }

    public function referredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by_user_id');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(User::class, 'referred_by_user_id');
    }

    public function referralUsage(): HasOne
    {
        return $this->hasOne(ReferralUsage::class, 'referee_id');
    }

    public function referralUsagesGiven(): HasMany
    {
        return $this->hasMany(ReferralUsage::class, 'referrer_id');
    }

    public function hasPurchased(): bool
    {
        return $this->qualifyingOrders()->exists();
    }

    public function qualifyingOrders(): HasMany
    {
        return $this->orders()
            ->whereNotIn('status', ['cancelled', 'returned'])
            ->whereIn('payment_status', ['paid', 'partially_refunded'])
            ->whereRaw('(total - COALESCE(refunded_amount, 0)) > 0');
    }

    public function latestQualifyingPurchase(): ?Order
    {
        return $this->qualifyingOrders()
            ->latest('updated_at')
            ->first();
    }

    public function pushSegment(): string
    {
        if ($this->hasPurchased()) {
            return NotificationSchedule::SEGMENT_PURCHASED;
        }

        return NotificationSchedule::SEGMENT_REGISTERED;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'wallet_balance' => 'decimal:2',
        ];
    }
}
