<?php

namespace App\Observers;

use App\Jobs\BroadcastNewSaleJob;
use App\Models\Coupon;
use Illuminate\Support\Facades\Cache;

class CouponObserver
{
    public function created(Coupon $coupon): void
    {
        if ($coupon->is_active && $coupon->is_public) {
            BroadcastNewSaleJob::dispatch($coupon->id);
        }
    }

    public function saved(Coupon $coupon): void
    {
        Cache::forget('site.public_coupons');

        if (! $coupon->is_active || ! $coupon->is_public) {
            return;
        }

        // Avoid duplicate dispatch if created() just dispatched it
        $shouldNotify = ! $coupon->wasRecentlyCreated
            && ($coupon->wasChanged('is_active') || $coupon->wasChanged('is_public'));

        if ($shouldNotify) {
            BroadcastNewSaleJob::dispatch($coupon->id);
        }
    }

    public function deleted(Coupon $coupon): void
    {
        Cache::forget('site.public_coupons');
    }
}
