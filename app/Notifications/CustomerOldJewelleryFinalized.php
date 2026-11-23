<?php

namespace App\Notifications;

use App\Models\OldJewelleryWalletCredit;
use App\Notifications\Channels\FcmPushChannel;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomerOldJewelleryFinalized extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly OldJewelleryWalletCredit $credit) {}

    public function via(object $notifiable): array
    {
        $channels = ['mail', 'database'];

        if (filled($notifiable->phone ?? null)) {
            $channels[] = WhatsAppChannel::class;
        }

        if (method_exists($notifiable, 'deviceTokens') && $notifiable->deviceTokens()->exists()) {
            $channels[] = FcmPushChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $request = $this->credit->request;

        return (new MailMessage)
            ->subject("Your old jewellery valuation is complete — {$request->request_number}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your old jewellery request {$request->request_number} has been valued.")
            ->line("₹{$this->credit->credited_amount} has been credited to your wallet.")
            ->line("This credit expires on {$this->credit->expires_at->format('d M Y')}.")
            ->action('Shop now', url('/'));
    }

    public function toWhatsApp(object $notifiable): string
    {
        $request = $this->credit->request;

        return "Your old jewellery request {$request->request_number} has been valued. ₹{$this->credit->credited_amount} credited to your wallet (expires {$this->credit->expires_at->format('d M Y')}).";
    }

    public function toArray(object $notifiable): array
    {
        $request = $this->credit->request;
        $creditedFormatted = number_format((float) $this->credit->credited_amount);

        return [
            'category' => 'sell_jewellery',
            'title' => "₹{$creditedFormatted} Credited to Wallet 🎉",
            'body' => "Your request {$request->request_number} was valued at ₹{$this->credit->gross_amount}. ₹{$creditedFormatted} has been credited to your Estele Wallet.",
            'action' => 'wallet',
            'action_id' => $request->request_number,
            'channelId' => 'estele_sell_jewellery',
            'data' => [
                'type' => 'sell_jewellery',
                'category' => 'sell_jewellery',
                'targetType' => 'wallet',
                'requestNumber' => $request->request_number,
                'requestId' => $request->id,
                'finalAmount' => (float) $this->credit->gross_amount,
                'creditedAmount' => (float) $this->credit->credited_amount,
                'referenceNumber' => $this->credit->reference_number ?? ('REQ-' . $request->request_number),
                'sellingStatus' => 'sold',
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
}
