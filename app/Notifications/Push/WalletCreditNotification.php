<?php

namespace App\Notifications\Push;

use App\Notifications\Concerns\HasFcmPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class WalletCreditNotification extends Notification implements ShouldQueue
{
    use HasFcmPush, Queueable;

    public function __construct(
        public readonly float $amount,
        public readonly string $reason,
        public readonly string $type = 'credit',
        public readonly ?float $balanceAfter = null,
    ) {}

    public function via(object $notifiable): array
    {
        return $this->pushChannels($notifiable);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'wallet',
            'title' => $this->title(),
            'body' => $this->body(),
            'action' => 'wallet',
            'data' => [
                'type' => 'wallet',
                'action' => 'wallet',
                'amount' => $this->amount,
                'balanceAfter' => $this->balanceAfter,
                'walletType' => $this->type,
            ],
        ];
    }

    public function toExpoPush(object $notifiable): array
    {
        return $this->toFcmPush($notifiable);
    }

    public function toFcmPush(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body(),
            'channelId' => 'estele_sell_jewellery',
            'data' => [
                'type' => 'wallet',
                'action' => 'wallet',
                'amount' => (string) $this->amount,
                'balanceAfter' => $this->balanceAfter !== null ? (string) $this->balanceAfter : '',
                'walletType' => $this->type,
            ],
        ];
    }

    public function title(): string
    {
        if ($this->type === 'debit') {
            return 'Wallet Debited';
        }

        return match ($this->reason) {
            'order_refund' => 'Refund Credited to Wallet 💰',
            default => 'Wallet Credited 🎉',
        };
    }

    public function body(): string
    {
        $formatted = '₹' . number_format($this->amount, 2);

        if ($this->type === 'debit') {
            $balance = $this->balanceAfter !== null ? ' Balance: ₹' . number_format($this->balanceAfter, 2) . '.' : '';
            return "{$formatted} has been used from your wallet.{$balance}";
        }

        return match ($this->reason) {
            'order_refund' => "{$formatted} has been refunded to your wallet.",
            'reward', 'referral_reward' => "{$formatted} reward has been credited to your wallet.",
            'old_jewellery_sale' => "{$formatted} has been credited to your wallet for old jewellery.",
            default => "{$formatted} has been credited to your wallet.",
        };
    }
}
