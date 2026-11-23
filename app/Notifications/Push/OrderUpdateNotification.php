<?php

namespace App\Notifications\Push;

use App\Models\Order;
use App\Notifications\Concerns\HasFcmPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class OrderUpdateNotification extends Notification implements ShouldQueue
{
    use HasFcmPush, Queueable;

    public readonly string $event;

    public function __construct(
        public readonly Order $order,
        public readonly string $status = 'placed',
    ) {
        $this->event = $status;
    }

    public function via(object $notifiable): array
    {
        return $this->pushChannels($notifiable);
    }

    public function databaseType(): string
    {
        return \App\Notifications\OrderUpdateNotification::class;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'order',
            'title' => $this->title(),
            'body' => $this->body(),
            'action' => 'order',
            'action_id' => $this->order->order_number,
            'channelId' => 'estele_orders',
            'data' => [
                'type' => 'order',
                'action' => 'order',
                'action_id' => $this->order->order_number,
                'status' => $this->status,
                'orderNumber' => $this->order->order_number,
            ],
        ];
    }

    public function toExpoPush(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body(),
            'channelId' => 'estele_orders',
            'data' => [
                'type' => 'order',
                'action' => 'order',
                'action_id' => $this->order->order_number,
                'status' => $this->status,
                'orderNumber' => $this->order->order_number,
            ],
        ];
    }

    public function toFcmPush(object $notifiable): array
    {
        return $this->toExpoPush($notifiable);
    }

    public function title(): string
    {
        $orderNumber = $this->order->order_number;

        return match ($this->status) {
            'placed' => 'Order Placed Successfully 🛍️',
            'accepted', 'confirmed' => 'Order Confirmed & Accepted ✨',
            'payment_confirmed', 'paid' => 'Payment Confirmed 💳',
            'packed' => 'Order Packed & Ready 📦',
            'shipped' => "Order #{$orderNumber} Dispatched 🚚",
            'out_for_delivery' => "Order #{$orderNumber} Out for Delivery 🛵",
            'delivered' => 'Order Delivered 🎉',
            'cancelled' => 'Order Cancelled',
            'returned' => 'Order Returned',
            'refund', 'refunded' => 'Order Refund Processed 💰',
            default => 'Order Update',
        };
    }

    public function body(): string
    {
        $number = $this->order->order_number;
        $totalFormatted = number_format((float) $this->order->total, 2);

        return match ($this->status) {
            'placed' => "Thank you! Your Estele order #{$number} for ₹{$totalFormatted} has been placed.",
            'accepted', 'confirmed' => "Your order #{$number} is confirmed and in preparation.",
            'payment_confirmed', 'paid' => "Payment of ₹{$totalFormatted} for your order #{$number} has been confirmed. Thank you!",
            'packed' => "Your jewellery parcel for order #{$number} is packed and ready for dispatch.",
            'shipped' => "Your parcel is on its way" . ($this->order->carrier ? " via {$this->order->carrier}" : "") . ($this->order->tracking_number ? " (Tracking #{$this->order->tracking_number})" : "") . ".",
            'out_for_delivery' => "Your order #{$number} is out for delivery. It will arrive today!",
            'delivered' => "Your Estele order #{$number} has been delivered. Enjoy your handcrafted jewellery!",
            'cancelled' => "Your order #{$number} has been cancelled.",
            'returned' => "Your order #{$number} return has been processed.",
            'refund', 'refunded' => "A refund has been processed for your order #{$number}.",
            default => "There's an update on your order #{$number}.",
        };
    }
}
