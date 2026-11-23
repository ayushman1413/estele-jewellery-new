<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ReferralUsage;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReferralService
{
    // Wallet credits awarded on the referee's first completed order.
    public const REFERRER_CREDIT = 100.0;

    public const REFEREE_CREDIT = 50.0;

    public function __construct(private readonly WalletService $wallet) {}

    /**
     * Called once after an order is placed. Checks whether this is the
     * referee's very first order and, if so, credits both parties.
     * Safe to call on every order — the unique(referee_id) constraint on
     * referral_usages guarantees idempotency.
     */
    public function maybeReward(Order $order): void
    {
        $referee = $order->user;

        if (! $referee || ! $referee->referred_by_user_id) {
            return;
        }

        // Already rewarded for a previous order from this user.
        if (ReferralUsage::where('referee_id', $referee->id)->exists()) {
            return;
        }

        $referrer = User::find($referee->referred_by_user_id);

        if (! $referrer) {
            return;
        }

        DB::transaction(function () use ($referrer, $referee, $order) {
            // Double-check inside the transaction to avoid a race between two
            // simultaneous first-order submissions.
            if (ReferralUsage::where('referee_id', $referee->id)->lockForUpdate()->exists()) {
                return;
            }

            ReferralUsage::create([
                'referrer_id' => $referrer->id,
                'referee_id' => $referee->id,
                'order_id' => $order->id,
                'referrer_credit' => self::REFERRER_CREDIT,
                'referee_credit' => self::REFEREE_CREDIT,
            ]);

            $this->wallet->credit($referrer, self::REFERRER_CREDIT, 'referral_reward', $order);
            $this->wallet->credit($referee, self::REFEREE_CREDIT, 'referral_reward', $order);
        });
    }
}
