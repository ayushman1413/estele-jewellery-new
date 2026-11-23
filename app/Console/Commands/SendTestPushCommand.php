<?php

namespace App\Console\Commands;

use App\Services\PushNotificationService;
use Illuminate\Console\Command;

class SendTestPushCommand extends Command
{
    protected $signature = 'push:test {token? : FCM device token to send to} {--topic= : Send to topic instead of token}';

    protected $description = 'Send a test push notification to verify Firebase delivery';

    public function handle(PushNotificationService $service): int
    {
        $payload = [
            'title' => 'Estele Test',
            'body' => 'Push notification delivery verified at ' . now()->toDateTimeString(),
            'channelId' => 'estele_general',
            'data' => [
                'type' => 'test',
                'action' => 'test',
            ],
        ];

        if ($topic = $this->option('topic')) {
            $this->info("Sending to topic: {$topic}");
            $result = $service->sendToTopic($topic, $payload);
        } else {
            $token = $this->argument('token');
            if (! $token) {
                $token = \App\Models\DeviceToken::whereNotNull('device_token')
                    ->where('device_token', '!=', '')
                    ->latest()
                    ->value('device_token');
            }

            if (! $token) {
                $this->error('No FCM token found. Pass a token argument or register a device first.');
                return self::FAILURE;
            }

            $this->info("Sending to token: {$token}");
            $result = $service->send($token, $payload);
        }

        if ($result['success'] ?? false) {
            $simulated = $result['simulated'] ?? false;
            $this->info($simulated ? 'SIMULATED — credentials not configured yet.' : 'Push sent successfully.');
            $this->table(array_keys($result), [array_map(fn ($v) => is_array($v) ? json_encode($v) : $v, $result)]);
            return self::SUCCESS;
        }

        $this->error('Push failed.');
        $this->table(array_keys($result), [array_map(fn ($v) => is_array($v) ? json_encode($v) : $v, $result)]);
        return self::FAILURE;
    }
}
