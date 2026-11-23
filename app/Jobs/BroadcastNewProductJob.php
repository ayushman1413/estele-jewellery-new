<?php

namespace App\Jobs;

use App\Models\DeviceToken;
use App\Models\Product;
use App\Models\User;
use App\Notifications\ProductRecommendationNotification;
use App\Services\PushNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BroadcastNewProductJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public readonly ?Product $product;
    public readonly int $productId;

    public function __construct(int|Product $productParam)
    {
        if ($productParam instanceof Product) {
            $this->product = $productParam;
            $this->productId = $productParam->id;
        } else {
            $this->productId = (int) $productParam;
            $this->product = Product::find($productParam);
        }
    }

    public function handle(): void
    {
        $product = $this->product ?: Product::find($this->productId);
        if (! $product || ! $product->is_active) {
            return;
        }

        $image = $product->getFirstMediaUrl('images', 'thumb') ?: null;
        $title = 'New Jewellery Arrival ✨';
        $body = "Explore {$product->title} — handcrafted elegance just added to our collection.";

        // 1. Record database notification for registered users so they see it in the app notification center
        User::whereHas('deviceTokens')
            ->cursor()
            ->each(function (User $user) use ($title, $body, $image, $product) {
                try {
                    $user->notify(new ProductRecommendationNotification(
                        title: $title,
                        body: $body,
                        image: $image,
                        action: 'product',
                        actionId: $product->slug,
                        pushToDevice: false, // In-app database notification; push delivered globally via topic
                    ));
                } catch (\Throwable $e) {
                    report($e);
                }
            });

        // 2. Prepare payload for global promotional topic (estele_all_customers)
        $payload = [
            'title' => $title,
            'body' => $body,
            'channelId' => 'estele_promotions',
            'image' => $image,
            'data' => [
                'type' => 'product',
                'targetType' => 'product',
                'action' => 'product',
                'action_id' => $product->slug,
                'slug' => $product->slug,
                'productId' => (string) $product->id,
            ],
        ];

        // 3. Dispatch to global promotional topic for ALL customers (guest, registered, purchased)
        app(PushNotificationService::class)->sendToTopic(PushNotificationService::PROMOTIONAL_TOPIC, $payload);
    }
}

