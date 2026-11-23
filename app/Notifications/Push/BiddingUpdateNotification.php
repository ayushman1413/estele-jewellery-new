<?php

namespace App\Notifications\Push;

use App\Models\OldJewelleryRequest;
use App\Notifications\Concerns\HasFcmPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class BiddingUpdateNotification extends Notification implements ShouldQueue
{
    use HasFcmPush, Queueable;

    public function __construct(
        public readonly OldJewelleryRequest $request,
        public readonly string $event,
        public readonly mixed $bid = null,
        public readonly ?float $amount = null,
    ) {}

    public function via(object $notifiable): array
    {
        return $this->pushChannels($notifiable);
    }

    public function databaseType(): string
    {
        return \App\Notifications\BiddingUpdateNotification::class;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'bidding_updates',
            'title' => $this->title(),
            'body' => $this->body(),
            'action' => 'sell_request',
            'action_id' => $this->request->request_number,
            'channelId' => 'estele_bidding',
            'data' => [
                'type' => 'bidding',
                'category' => 'bidding_updates',
                'action' => 'sell_request',
                'action_id' => $this->request->request_number,
                'requestNumber' => $this->request->request_number,
                'event' => $this->event,
                'amount' => $this->resolveAmount(),
            ],
        ];
    }

    public function toExpoPush(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body(),
            'channelId' => 'estele_bidding',
            'data' => [
                'type' => 'bidding',
                'category' => 'bidding_updates',
                'action' => 'sell_request',
                'action_id' => $this->request->request_number,
                'requestNumber' => $this->request->request_number,
                'event' => $this->event,
                'amount' => $this->resolveAmount(),
            ],
        ];
    }

    public function toFcmPush(object $notifiable): array
    {
        return $this->toExpoPush($notifiable);
    }

    public function resolveAmount(): ?float
    {
        if ($this->amount !== null) {
            return (float) $this->amount;
        }

        if (is_numeric($this->bid)) {
            return (float) $this->bid;
        }

        if (is_object($this->bid) && isset($this->bid->amount)) {
            return (float) $this->bid->amount;
        }

        return null;
    }

    public function getResolvedAmount(): ?float
    {
        return $this->resolveAmount();
    }

    public function title(): string
    {
        $amt = $this->resolveAmount();
        $formatted = $amt ? "₹" . number_format($amt) : null;

        return match ($this->event) {
            'new_bid' => $formatted ? "New Bid Received: {$formatted}" : 'New Bid Received',
            'higher_bid' => $formatted ? "New Highest Bid Received: {$formatted}" : 'Higher Bid Received',
            'outbid' => 'You Have Been Outbid',
            'bid_accepted' => 'Bid Accepted',
            'bid_rejected' => 'Bid Rejected',
            'bid_cancelled' => 'Bid Cancelled',
            'bidding_closed', 'closed' => 'Bidding Closed',
            default => 'Bidding Update',
        };
    }

    public function body(): string
    {
        $number = $this->request->request_number;
        $amt = $this->resolveAmount();
        $formatted = $amt ? "₹" . number_format($amt) : '';

        return match ($this->event) {
            'new_bid' => "A new bid {$formatted} has been placed on your jewellery request {$number}.",
            'higher_bid' => "A higher bid {$formatted} has been placed on your jewellery request {$number}.",
            'outbid' => "A higher bid has been placed on request {$number}. Increase your bid to stay in the running.",
            'bid_accepted' => "A bid on your jewellery request {$number} has been accepted.",
            'bid_rejected' => "The bid on your jewellery request {$number} was not accepted.",
            'bid_cancelled' => "The bid on your jewellery request {$number} has been cancelled.",
            'bidding_closed', 'closed' => "Bidding on your jewellery request {$number} has ended.",
            default => "There is an update on your jewellery bidding request {$number}.",
        };
    }
}
