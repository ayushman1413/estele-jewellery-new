<?php

namespace App\Notifications\Channels;

use App\Services\PushNotificationService;
use Illuminate\Notifications\Notification;

class FcmPushChannel
{
    public function __construct(protected PushNotificationService $pushService) {}

    public function send(mixed $notifiable, Notification $notification): void
    {
        $payload = [];
        if (method_exists($notification, 'toFcmPush')) {
            $payload = $notification->toFcmPush($notifiable);
        } elseif (method_exists($notification, 'toExpoPush')) {
            $payload = $notification->toExpoPush($notifiable);
        } elseif (method_exists($notification, 'toArray')) {
            $payload = $notification->toArray($notifiable);
        }

        if (empty($payload)) {
            return;
        }

        $tokens = $this->resolveTokens($notifiable);
        if (empty($tokens)) {
            return;
        }

        $this->pushService->send($tokens, $payload);
    }

    protected function resolveTokens(mixed $notifiable): array
    {
        if (method_exists($notifiable, 'routeNotificationForFcmPush')) {
            $tokens = $notifiable->routeNotificationForFcmPush();
            return is_array($tokens) ? $tokens : [$tokens];
        }

        if (method_exists($notifiable, 'routeNotificationForExpoPush')) {
            $tokens = $notifiable->routeNotificationForExpoPush();
            return is_array($tokens) ? $tokens : [$tokens];
        }

        if (method_exists($notifiable, 'deviceTokens')) {
            $tokens = $notifiable->deviceTokens()
                ->get()
                ->map(fn ($dt) => $dt->device_token ?: ($dt->token ?: $dt->expo_push_token))
                ->filter()
                ->unique()
                ->values()
                ->all();

            if (! empty($tokens)) {
                return $tokens;
            }
        }

        if (! empty($notifiable->id)) {
            return \App\Models\DeviceToken::where('user_id', $notifiable->id)
                ->get()
                ->map(fn ($dt) => $dt->device_token ?: ($dt->token ?: $dt->expo_push_token))
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        return [];
    }
}
