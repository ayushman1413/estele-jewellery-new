<?php

namespace App\Notifications\Push;

use App\Models\OldJewelleryRequest;
use App\Notifications\Concerns\HasFcmPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SellJewelleryUpdateNotification extends Notification implements ShouldQueue
{
    use HasFcmPush, Queueable;

    public function __construct(
        public readonly OldJewelleryRequest $request,
        public readonly string $event,
        public readonly ?float $finalAmount = null,
        public readonly ?float $creditedAmount = null,
    ) {}

    public function via(object $notifiable): array
    {
        return $this->pushChannels($notifiable);
    }

    public function databaseType(): string
    {
        return \App\Notifications\SellJewelleryUpdateNotification::class;
    }

    public function toArray(object $notifiable): array
    {
        $sellingStatus = match ($this->event) {
            'submitted' => 'submitted',
            'pending' => 'pending',
            'bidding_started' => 'bidding_started',
            'approved', 'bid_selected', 'sold' => 'approved',
            'rejected' => 'rejected',
            'wallet_credited' => 'wallet_credited',
            default => $this->event,
        };

        return [
            'category' => 'sell_jewellery',
            'title' => $this->title(),
            'body' => $this->body(),
            'action' => 'sell_request',
            'action_id' => $this->request->request_number,
            'channelId' => 'estele_sell_jewellery',
            'data' => [
                'type' => 'sell_jewellery',
                'category' => 'sell_jewellery',
                'action' => 'sell_request',
                'action_id' => $this->request->request_number,
                'sellingStatus' => $sellingStatus,
                'finalAmount' => $this->finalAmount ?: ((float) $this->request->final_amount ?: null),
                'creditedAmount' => $this->creditedAmount ?: ((float) $this->request->credited_amount ?: null),
                'requestNumber' => $this->request->request_number,
            ],
        ];
    }

    public function toExpoPush(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }

    public function toFcmPush(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }

    public function title(): string
    {
        return match ($this->event) {
            'submitted' => 'Sell Jewellery Request Submitted 💍',
            'pending' => 'Request Under Review',
            'bidding_started' => 'Bidding Started',
            'approved', 'bid_selected', 'sold' => 'Jewellery Valuation Approved ✨',
            'rejected' => 'Valuation Request Not Approved',
            'wallet_credited' => 'Wallet Credited 🎉',
            default => 'Sell Jewellery Update',
        };
    }

    public function body(): string
    {
        $number = $this->request->request_number;
        $amount = $this->finalAmount ?: (float) $this->request->final_amount;
        $amtFormatted = $amount ? number_format($amount) : '';

        return match ($this->event) {
            'submitted' => "Your sell jewellery request {$number} has been submitted successfully.",
            'pending' => "Your sell jewellery request {$number} is being reviewed.",
            'bidding_started' => "Bidding has started on your jewellery request {$number}. Vendors are now placing their bids.",
            'approved', 'bid_selected', 'sold' => $amount
                ? "Your jewellery request {$number} was valued at ₹{$amtFormatted}."
                : "Your sell jewellery request {$number} has been approved.",
            'rejected' => "Your sell jewellery request {$number} was not approved.",
            'wallet_credited' => "₹" . number_format((float) ($this->creditedAmount ?: $this->request->credited_amount)) . " credited to your wallet for request {$number}.",
            default => "There is an update on your jewellery request {$number}.",
        };
    }
}
