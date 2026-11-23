<?php

namespace App\Notifications\Push;

use App\Notifications\Concerns\HasFcmPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ProductRecommendationNotification extends Notification implements ShouldQueue
{
    use HasFcmPush, Queueable;

    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly ?string $image = null,
        public readonly ?string $action = null,
        public readonly ?string $actionId = null,
        public readonly bool $pushToDevice = true,
    ) {}

    public function via(object $notifiable): array
    {
        if (! $this->pushToDevice) {
            return ['database'];
        }

        return $this->pushChannels($notifiable);
    }

    public function toArray(object $notifiable): array
    {
        return array_filter([
            'category' => 'recommendation',
            'title' => $this->title,
            'body' => $this->body,
            'image' => $this->image,
            'action' => $this->action,
            'action_id' => $this->actionId,
        ]);
    }

    public function toExpoPush(object $notifiable): array
    {
        return $this->toFcmPush($notifiable);
    }

    public function toFcmPush(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'channelId' => 'estele_promotions',
            'data' => array_filter([
                'type' => 'recommendation',
                'action' => $this->action,
                'action_id' => $this->actionId,
                'image' => $this->image,
            ]),
        ];
    }
}
