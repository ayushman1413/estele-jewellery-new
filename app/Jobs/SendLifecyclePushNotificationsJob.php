<?php

namespace App\Jobs;

use App\Models\Collection;
use App\Models\Coupon;
use App\Models\DeviceToken;
use App\Models\NotificationSchedule;
use App\Models\Product;
use App\Models\User;
use App\Notifications\Push\ProductRecommendationNotification;
use App\Services\PushNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendLifecyclePushNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(?PushNotificationService $service = null): void
    {
        $this->sendRegisteredUserNotifications();
        $this->sendPurchasedUserNotifications();
        $this->sendGuestNotifications($service);
    }

    private function sendRegisteredUserNotifications(): void
    {
        NotificationSchedule::where('segment', 'registered')
            ->where(function ($q) {
                $q->where('next_send_at', '<=', now())
                  ->orWhere('next_eligible_at', '<=', now());
            })
            ->whereNotNull('user_id')
            ->cursor()
            ->each(function (NotificationSchedule $schedule) {
                $user = User::find($schedule->user_id);
                if (! $user) {
                    $schedule->delete();
                    return;
                }

                if ($user->hasPurchased()) {
                    $latestOrder = $user->latestQualifyingPurchase();
                    $purchaseTime = null;
                    if ($latestOrder && ! empty($latestOrder->settlement_at)) {
                        $purchaseTime = \Illuminate\Support\Carbon::parse($latestOrder->settlement_at);
                    }
                    $nextEligible = $purchaseTime
                        ? $purchaseTime->copy()->addDays(NotificationSchedule::INTERVAL_PURCHASED_DAYS)
                        : now()->addDays(NotificationSchedule::INTERVAL_PURCHASED_DAYS);

                    if ($nextEligible->isPast()) {
                        $nextEligible = now()->addDays(NotificationSchedule::INTERVAL_PURCHASED_DAYS);
                    }

                    $schedule->update([
                        'segment' => NotificationSchedule::SEGMENT_PURCHASED,
                        'next_send_at' => $nextEligible,
                        'next_eligible_at' => $nextEligible,
                    ]);
                    return;
                }

                $recommendation = $this->buildRecommendation('registered');
                if ($recommendation) {
                    try {
                        $user->notify($recommendation);
                    } catch (\Throwable $e) {
                        report($e);
                    }
                }

                $schedule->update([
                    'last_sent_at' => now(),
                    'last_notified_at' => now(),
                    'next_send_at' => now()->addDays(NotificationSchedule::INTERVAL_REGISTERED_DAYS),
                    'next_eligible_at' => now()->addDays(NotificationSchedule::INTERVAL_REGISTERED_DAYS),
                ]);
            });
    }

    private function sendPurchasedUserNotifications(): void
    {
        NotificationSchedule::where('segment', 'purchased')
            ->where(function ($q) {
                $q->where('next_send_at', '<=', now())
                  ->orWhere('next_eligible_at', '<=', now());
            })
            ->whereNotNull('user_id')
            ->cursor()
            ->each(function (NotificationSchedule $schedule) {
                $user = User::find($schedule->user_id);
                if (! $user) {
                    $schedule->delete();
                    return;
                }

                if (! $user->hasPurchased()) {
                    // Recalculated after refund or cancellation: downgrade to registered
                    $schedule->update([
                        'segment' => NotificationSchedule::SEGMENT_REGISTERED,
                        'next_send_at' => now()->addDays(NotificationSchedule::INTERVAL_REGISTERED_DAYS),
                        'next_eligible_at' => now()->addDays(NotificationSchedule::INTERVAL_REGISTERED_DAYS),
                    ]);
                    return;
                }

                $recommendation = $this->buildRecommendation('purchased');
                if ($recommendation) {
                    try {
                        $user->notify($recommendation);
                    } catch (\Throwable $e) {
                        report($e);
                    }
                }

                $latestOrder = $user->latestQualifyingPurchase();
                $purchaseTime = null;
                if ($latestOrder && ! empty($latestOrder->settlement_at)) {
                    $purchaseTime = \Illuminate\Support\Carbon::parse($latestOrder->settlement_at);
                }
                $nextEligible = $purchaseTime
                    ? $purchaseTime->copy()->addDays(NotificationSchedule::INTERVAL_PURCHASED_DAYS)
                    : now()->addDays(NotificationSchedule::INTERVAL_PURCHASED_DAYS);

                if ($nextEligible->isPast()) {
                    $nextEligible = now()->addDays(NotificationSchedule::INTERVAL_PURCHASED_DAYS);
                }

                $schedule->update([
                    'last_sent_at' => now(),
                    'last_notified_at' => now(),
                    'next_send_at' => $nextEligible,
                    'next_eligible_at' => $nextEligible,
                ]);
            });
    }

    private function sendGuestNotifications(?PushNotificationService $service = null): void
    {
        $pushService = $service ?: app(PushNotificationService::class);

        NotificationSchedule::where('segment', 'guest')
            ->where(function ($q) {
                $q->where('next_send_at', '<=', now())
                  ->orWhere('next_eligible_at', '<=', now());
            })
            ->whereNull('user_id')
            ->cursor()
            ->each(function (NotificationSchedule $schedule) use ($pushService) {
                $token = DeviceToken::where('expo_push_token', $schedule->device_token_id)
                    ->orWhere('device_token', $schedule->device_token_id)
                    ->orWhere('device_id', $schedule->device_id)
                    ->first();

                if (! $token) {
                    $schedule->delete();
                    return;
                }

                if ($token->user_id) {
                    $user = User::find($token->user_id);
                    $segment = $user ? $user->pushSegment() : NotificationSchedule::SEGMENT_REGISTERED;
                    $days = $segment === NotificationSchedule::SEGMENT_PURCHASED
                        ? NotificationSchedule::INTERVAL_PURCHASED_DAYS
                        : NotificationSchedule::INTERVAL_REGISTERED_DAYS;

                    $schedule->update([
                        'user_id' => $token->user_id,
                        'segment' => $segment,
                        'next_send_at' => now()->addDays($days),
                        'next_eligible_at' => now()->addDays($days),
                    ]);
                    return;
                }

                $recommendation = $this->buildRecommendation('guest');
                if ($recommendation) {
                    $push = $recommendation->toExpoPush(new \stdClass);
                    $targetToken = $token->device_token ?: ($token->token ?: $token->expo_push_token);

                    if ($targetToken) {
                        $pushService->send($targetToken, [
                            'title' => $push['title'] ?? 'Estele Special Offer',
                            'body' => $push['body'] ?? '',
                            'channelId' => 'estele_promotions',
                            'data' => $push['data'] ?? [],
                        ]);
                    }
                }

                $schedule->update([
                    'last_sent_at' => now(),
                    'last_notified_at' => now(),
                    'next_send_at' => now()->addDays(NotificationSchedule::INTERVAL_GUEST_DAYS),
                    'next_eligible_at' => now()->addDays(NotificationSchedule::INTERVAL_GUEST_DAYS),
                ]);
            });
    }

    private function buildRecommendation(string $segment): ?ProductRecommendationNotification
    {
        $activeSale = Coupon::where('is_active', true)
            ->where('is_public', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()))
            ->first();

        if ($activeSale) {
            return new ProductRecommendationNotification(
                title: 'Sale is Live!',
                body: $activeSale->summary().". Use code {$activeSale->code} at checkout.",
                action: 'sale',
                actionId: $activeSale->code,
            );
        }

        $newCollection = Collection::where('is_active', true)
            ->latest()
            ->first();

        if ($newCollection) {
            return new ProductRecommendationNotification(
                title: 'New Collection',
                body: "Check out our {$newCollection->name} collection.",
                action: 'collection',
                actionId: $newCollection->slug,
            );
        }

        $newProduct = Product::where('is_active', true)
            ->latest()
            ->first();

        if ($newProduct) {
            $image = $newProduct->getFirstMediaUrl('images', 'thumb') ?: null;

            return new ProductRecommendationNotification(
                title: 'New Arrival',
                body: "Check out {$newProduct->title} — just added to our store.",
                image: $image,
                action: 'product',
                actionId: $newProduct->slug,
            );
        }

        $featured = Product::where('is_active', true)
            ->where('is_featured', true)
            ->inRandomOrder()
            ->first();

        if ($featured) {
            return new ProductRecommendationNotification(
                title: 'Recommended for You',
                body: "You might love {$featured->title}.",
                action: 'product',
                actionId: $featured->slug,
            );
        }

        return null;
    }
}
