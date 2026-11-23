<?php

namespace App\Notifications\Push;

use App\Models\Coupon;
use App\Notifications\Concerns\HasFcmPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class NewSaleNotification extends Notification implements ShouldQueue
{
    use HasFcmPush, Queueable;

    public function __construct(
        public readonly Coupon $coupon,
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
        return [
            'category' => 'sale',
            'title' => 'Sale Alert!',
            'body' => $this->body(),
            'action' => 'sale',
            'action_id' => $this->coupon->code,
        ];
    }

    public function toExpoPush(object $notifiable): array
    {
        return $this->toFcmPush($notifiable);
    }

    public function toFcmPush(object $notifiable): array
    {
        return [
            'title' => 'Sale Alert!',
            'body' => $this->body(),
            'channelId' => 'estele_promotions',
            'data' => [
                'type' => 'sale',
                'action' => 'sale',
                'coupon_code' => $this->coupon->code,
            ],
        ];
    }

    private function body(): string
    {
        $summary = $this->coupon->summary();

        return "New sale: {$summary}. Use code {$this->coupon->code} at checkout.";
    }
}
