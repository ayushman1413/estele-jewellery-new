<?php

namespace App\Notifications\Concerns;

use App\Notifications\Channels\FcmPushChannel;

trait HasFcmPush
{
    /**
     * Dispatch notifications directly to Android phone push channel.
     * Ensures native Android heads-up presentation with zero in-app badge accumulation.
     */
    protected function pushChannels(object $notifiable): array
    {
        $channels = [FcmPushChannel::class];

        // Also store record in database so mobile app can background-sync and immediately display to Android tray
        $channels[] = 'database';

        return $channels;
    }
}
