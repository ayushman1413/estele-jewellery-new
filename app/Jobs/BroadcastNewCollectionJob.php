<?php

namespace App\Jobs;

use App\Models\Collection;
use App\Models\DeviceToken;
use App\Models\User;
use App\Notifications\ProductRecommendationNotification;
use App\Services\PushNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BroadcastNewCollectionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public readonly ?Collection $collection;
    public readonly int $collectionId;

    public function __construct(int|Collection $collectionParam)
    {
        if ($collectionParam instanceof Collection) {
            $this->collection = $collectionParam;
            $this->collectionId = $collectionParam->id;
        } else {
            $this->collectionId = (int) $collectionParam;
            $this->collection = Collection::find($collectionParam);
        }
    }

    public function handle(): void
    {
        $collection = $this->collection ?: Collection::find($this->collectionId);
        if (! $collection || ! $collection->is_active) {
            return;
        }

        $image = $collection->getFirstMediaUrl('image', 'thumb') ?: null;
        $title = 'New Collection Launch 🌟';
        $body = "Discover our latest {$collection->name} collection — timeless jewellery pieces.";

        // 1. Record database notification for registered users so they see it in the app notification center
        User::whereHas('deviceTokens')
            ->cursor()
            ->each(function (User $user) use ($title, $body, $image, $collection) {
                try {
                    $user->notify(new ProductRecommendationNotification(
                        title: $title,
                        body: $body,
                        image: $image,
                        action: 'collection',
                        actionId: $collection->slug,
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
                'type' => 'collection',
                'targetType' => 'collection',
                'action' => 'collection',
                'action_id' => $collection->slug,
                'slug' => $collection->slug,
                'collectionSlug' => $collection->slug,
                'collectionId' => (string) $collection->id,
            ],
        ];

        // 3. Dispatch to global promotional topic for ALL customers (guest, registered, purchased)
        app(PushNotificationService::class)->sendToTopic(PushNotificationService::PROMOTIONAL_TOPIC, $payload);
    }
}

