<?php

namespace App\Services;

class FcmService
{
    public function __construct(protected ?PushNotificationService $pushService = null)
    {
        $this->pushService = $pushService ?: app(PushNotificationService::class);
    }

    public function sendToTokens(array $tokens, array $notification, array $data = [], ?string $androidChannelId = null): void
    {
        $payload = [
            'title' => $notification['title'] ?? '',
            'body' => $notification['body'] ?? '',
            'channelId' => $androidChannelId,
            'data' => $data,
        ];

        $this->pushService->send($tokens, $payload);
    }
}
