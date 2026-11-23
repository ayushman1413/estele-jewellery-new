<?php

namespace App\Notifications;

use App\Models\OldJewelleryWalletCredit;
use App\Notifications\Channels\FcmPushChannel;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OldJewelleryWalletExpiryReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly OldJewelleryWalletCredit $credit) {}

    public function via(object $notifiable): array
    {
        $channels = ['mail', 'database'];

        if (filled($notifiable->phone)) {
            $channels[] = WhatsAppChannel::class;
        }

        if (method_exists($notifiable, 'deviceTokens') && $notifiable->deviceTokens()->exists()) {
            $channels[] = FcmPushChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your old jewellery wallet credit is expiring soon')
            ->greeting("Hello {$notifiable->name},")
            ->line("₹{$this->credit->remaining_amount} from request {$this->credit->request->request_number} expires on {$this->credit->expires_at->format('d M Y')}.")
            ->action('Use it now', url('/'));
    }

    public function toWhatsApp(object $notifiable): string
    {
        return "Reminder: ₹{$this->credit->remaining_amount} wallet credit from request {$this->credit->request->request_number} expires on {$this->credit->expires_at->format('d M Y')}. Use it before it expires!";
    }

    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'wallet',
            'title' => 'Wallet Credit Expiring Soon',
            'body' => "₹{$this->credit->remaining_amount} from request {$this->credit->request->request_number} expires on {$this->credit->expires_at->format('d M Y')}.",
            'action' => 'wallet',
            'action_id' => $this->credit->request->request_number,
        ];
    }

    public function toExpoPush(object $notifiable): array
    {
        return $this->toFcmPush($notifiable);
    }

    public function toFcmPush(object $notifiable): array
    {
        return [
            'title' => 'Wallet Credit Expiring Soon',
            'body' => "₹{$this->credit->remaining_amount} expires on {$this->credit->expires_at->format('d M Y')}. Use it before it expires!",
            'channelId' => 'estele_sell_jewellery',
            'data' => [
                'type' => 'wallet',
                'action' => 'wallet',
                'action_id' => $this->credit->request->request_number,
            ],
        ];
    }
}
