<?php

namespace App\Jobs;

use App\Models\Coupon;
use App\Models\DeviceToken;
use App\Models\User;
use App\Notifications\NewSaleNotification;
use App\Services\PushNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendNewSalePushNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public readonly ?Coupon $coupon;
    public readonly int $couponId;

    public function __construct(int|Coupon $couponParam)
    {
        if ($couponParam instanceof Coupon) {
            $this->coupon = $couponParam;
            $this->couponId = $couponParam->id;
        } else {
            $this->couponId = (int) $couponParam;
            $this->coupon = Coupon::find($couponParam);
        }
    }

    public function handle(): void
    {
        $coupon = $this->coupon ?: Coupon::find($this->couponId);
        if (! $coupon || ! $coupon->is_active || ! $coupon->is_public) {
            return;
        }

        // 1. Record database notification for registered users so they see it in the app notification center
        User::whereHas('deviceTokens')
            ->cursor()
            ->each(function (User $user) use ($coupon) {
                try {
                    $user->notify(new NewSaleNotification($coupon, pushToDevice: false));
                } catch (\Throwable $e) {
                    report($e);
                }
            });

        $summary = $coupon->summary();
        $body = "New sale: {$summary}. Use code {$coupon->code} at checkout.";
        $payload = [
            'title' => 'Sale Alert!',
            'body' => $body,
            'channelId' => 'estele_promotions',
            'data' => [
                'type' => 'sale',
                'targetType' => 'sale',
                'action' => 'sale',
                'action_id' => $coupon->code,
                'coupon_code' => $coupon->code,
            ],
        ];

        // 2. Dispatch to global promotional topic for ALL customers (guest, registered, purchased)
        app(PushNotificationService::class)->sendToTopic(PushNotificationService::PROMOTIONAL_TOPIC, $payload);
    }
}
