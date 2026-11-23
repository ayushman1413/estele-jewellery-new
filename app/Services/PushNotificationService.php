<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    public const DEFAULT_CHANNEL = 'estele_general';
    public const PROMOTIONAL_TOPIC = 'estele_all_customers';
    public const DEMO_TOPIC = 'estele_demo_testers';

    /**
     * Map friendly channel names to native Android notification channel IDs.
     */
    public const CHANNELS = [
        'orders' => 'estele_orders',
        'estele_orders' => 'estele_orders',
        'order_updates' => 'estele_orders',
        'estele_order_updates' => 'estele_orders',
        'bidding' => 'estele_bidding',
        'estele_bidding' => 'estele_bidding',
        'bidding_updates' => 'estele_bidding',
        'estele_bidding_updates' => 'estele_bidding',
        'sell_jewellery' => 'estele_sell_jewellery',
        'estele_sell_jewellery' => 'estele_sell_jewellery',
        'wallet' => 'estele_sell_jewellery',
        'estele_wallet' => 'estele_sell_jewellery',
        'promotions' => 'estele_promotions',
        'estele_promotions' => 'estele_promotions',
        'general' => 'estele_general',
        'estele_general' => 'estele_general',
    ];

    /**
     * Send push notification(s) to device tokens using Firebase Cloud Messaging (FCM HTTP v1).
     *
     * @param string|array $tokens Single token or array of FCM tokens
     * @param array $payload Notification attributes (title, body, channelId, data, etc.)
     * @return array Results including sent, failed counts
     */
    public function send(string|array $tokens, array $payload): array
    {
        $tokens = array_values(array_unique(array_filter((array) $tokens)));

        if (empty($tokens)) {
            return [
                'success' => true,
                'sent' => 0,
                'failed' => 0,
                'results' => [],
            ];
        }

        $projectId = config('services.firebase.project_id', env('FIREBASE_PROJECT_ID'));
        $credentialsPath = $this->resolveCredentialsPath();

        $sentCount = 0;
        $failedCount = 0;
        $results = [];

        // If in test environment or credentials not provided, simulate delivery gracefully
        if (app()->environment('testing') || empty($projectId) || (! file_exists($credentialsPath) && empty(env('FIREBASE_CREDENTIALS_JSON')))) {
            Log::info('FCM push simulated (testing or credentials not yet configured)', [
                'recipient_count' => count($tokens),
                'title' => $payload['title'] ?? '',
                'channelId' => $payload['channelId'] ?? self::DEFAULT_CHANNEL,
            ]);

            return [
                'success' => true,
                'simulated' => true,
                'sent' => count($tokens),
                'failed' => 0,
                'results' => array_map(fn ($t) => ['token' => $t, 'status' => 'simulated'], $tokens),
            ];
        }

        $accessToken = $this->getOAuth2AccessToken($projectId, $credentialsPath);
        if (! $accessToken) {
            Log::warning('Failed to retrieve FCM OAuth2 access token.');
            return [
                'success' => false,
                'sent' => 0,
                'failed' => count($tokens),
                'results' => [],
            ];
        }

        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
        $channelId = self::CHANNELS[$payload['channelId'] ?? ''] ?? self::DEFAULT_CHANNEL;

        foreach ($tokens as $token) {
            $message = $this->buildFcmMessage($token, $payload, $channelId);

            try {
                $response = Http::timeout(10)
                    ->withToken($accessToken)
                    ->asJson()
                    ->acceptJson()
                    ->post($url, ['message' => $message]);

                if ($response->successful()) {
                    $sentCount++;
                    $results[] = ['token' => $token, 'status' => 'sent', 'message_id' => $response->json('name')];
                } else {
                    $failedCount++;
                    $errorData = $response->json('error', []);
                    $results[] = ['token' => $token, 'status' => 'error', 'details' => $errorData];

                    // Remove invalid/unregistered device tokens
                    $status = $errorData['status'] ?? '';
                    $errorCode = $errorData['details'][0]['errorCode'] ?? '';
                    if (
                        in_array($status, ['NOT_FOUND', 'UNREGISTERED', 'INVALID_ARGUMENT'], true)
                        || in_array($errorCode, ['UNREGISTERED', 'INVALID_ARGUMENT'], true)
                    ) {
                        DeviceToken::where('device_token', $token)
                            ->orWhere('token', $token)
                            ->orWhere('expo_push_token', $token)
                            ->delete();
                    }
                }
            } catch (\Throwable $e) {
                $failedCount++;
                Log::warning('FCM HTTP v1 dispatch exception', ['token' => $token, 'error' => $e->getMessage()]);
            }
        }

        return [
            'success' => $sentCount > 0 || count($tokens) === 0,
            'sent' => $sentCount,
            'failed' => $failedCount,
            'results' => $results,
        ];
    }

    /**
     * Send push notification to all devices belonging to a user.
     */
    public function sendToUser(User $user, array $payload): array
    {
        $tokens = $user->deviceTokens()
            ->pluck('device_token')
            ->filter()
            ->all();

        // Also check legacy token and expo_push_token columns if device_token was null
        if (empty($tokens)) {
            $tokens = $user->deviceTokens()
                ->get()
                ->map(fn ($dt) => $dt->device_token ?: ($dt->token ?: $dt->expo_push_token))
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        return $this->send($tokens, $payload);
    }

    /**
     * Send push notification to a public broadcast topic (e.g. estele_demo_testers).
     *
     * SECURITY RULE: Never send private order, bidding, sell jewellery, or wallet notifications to a public topic.
     * Only public promotional and general broadcasts are permitted.
     *
     * @param string $topic Topic name without '/topics/' prefix
     * @param array $payload Notification attributes
     * @return array Results including success flag and message id
     */
    public function sendToTopic(string $topic, array $payload): array
    {
        $channelId = self::CHANNELS[$payload['channelId'] ?? ''] ?? self::DEFAULT_CHANNEL;
        $dataType = (string) ($payload['data']['type'] ?? $payload['data']['action'] ?? '');
        $dataCategory = (string) ($payload['data']['category'] ?? '');

        // Security Guard: Prevent any private customer notification from reaching a public topic
        $privateIndicators = ['order', 'bidding', 'sell_jewellery', 'wallet', 'orders', 'order_updates', 'bidding_updates'];
        if (
            in_array($channelId, ['estele_orders', 'estele_bidding', 'estele_sell_jewellery'], true)
            || in_array($dataType, $privateIndicators, true)
            || in_array($dataCategory, $privateIndicators, true)
        ) {
            Log::error('SECURITY ALERT: Blocked attempt to send private customer notification to public topic', [
                'topic' => $topic,
                'channelId' => $channelId,
                'dataType' => $dataType,
                'dataCategory' => $dataCategory,
            ]);

            return [
                'success' => false,
                'error' => 'Private notifications cannot be broadcast to public topics.',
            ];
        }

        $projectId = config('services.firebase.project_id', env('FIREBASE_PROJECT_ID'));
        $credentialsPath = $this->resolveCredentialsPath();

        if (app()->environment('testing') || empty($projectId) || (! file_exists($credentialsPath) && empty(env('FIREBASE_CREDENTIALS_JSON')))) {
            Log::info('FCM topic push simulated (testing or credentials not configured)', [
                'topic' => $topic,
                'title' => $payload['title'] ?? '',
                'channelId' => $channelId,
            ]);

            return [
                'success' => true,
                'simulated' => true,
                'topic' => $topic,
                'message_id' => 'simulated-' . uniqid(),
            ];
        }

        $accessToken = $this->getOAuth2AccessToken($projectId, $credentialsPath);
        if (! $accessToken) {
            Log::warning('Failed to retrieve FCM OAuth2 access token for topic broadcast.');
            return [
                'success' => false,
                'error' => 'Failed to retrieve FCM OAuth2 access token.',
            ];
        }

        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
        $message = $this->buildFcmMessage(null, $payload, $channelId, $topic);

        try {
            $response = Http::timeout(10)
                ->withToken($accessToken)
                ->asJson()
                ->acceptJson()
                ->post($url, ['message' => $message]);

            if ($response->successful()) {
                Log::info('FCM topic push successfully dispatched', [
                    'topic' => $topic,
                    'message_id' => $response->json('name'),
                ]);

                return [
                    'success' => true,
                    'topic' => $topic,
                    'message_id' => $response->json('name'),
                ];
            }

            $errorData = $response->json('error', []);
            Log::warning('FCM topic push failed', ['topic' => $topic, 'error' => $errorData]);

            return [
                'success' => false,
                'details' => $errorData,
            ];
        } catch (\Throwable $e) {
            Log::warning('FCM topic dispatch exception', ['topic' => $topic, 'error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Subscribe FCM device tokens to a topic.
     * Note: Under FCM HTTP v1 specifications, Google deprecated the server-side Instance ID (IID)
     * batchAdd API. Topic subscription is officially handled on the client device via the
     * Firebase Android SDK (messaging().subscribeToTopic()). This method provides a safe,
     * non-blocking API-compliant interface.
     *
     * @param string|array $tokens Single token or array of FCM tokens
     * @param string $topic Topic name without '/topics/' prefix (default: estele_all_customers)
     * @return array Results including success flag and count
     */
    public function subscribeToTopic(string|array $tokens, string $topic = self::PROMOTIONAL_TOPIC): array
    {
        $tokens = array_values(array_unique(array_filter((array) $tokens)));
        if (empty($tokens)) {
            return ['success' => true, 'subscribed' => 0];
        }

        Log::info('FCM topic subscription (handled client-side via Firebase Android SDK)', [
            'topic' => $topic,
            'count' => count($tokens),
        ]);

        return ['success' => true, 'subscribed' => count($tokens)];
    }

    /**
     * Unsubscribe FCM device tokens from a topic.
     * Note: Under FCM HTTP v1 specifications, topic unsubscription is officially handled on the
     * client device via the Firebase Android SDK (messaging().unsubscribeFromTopic()).
     *
     * @param string|array $tokens Single token or array of FCM tokens
     * @param string $topic Topic name without '/topics/' prefix (default: estele_all_customers)
     * @return array Results including success flag and count
     */
    public function unsubscribeFromTopic(string|array $tokens, string $topic = self::PROMOTIONAL_TOPIC): array
    {
        $tokens = array_values(array_unique(array_filter((array) $tokens)));
        if (empty($tokens)) {
            return ['success' => true, 'unsubscribed' => 0];
        }

        Log::info('FCM topic unsubscription (handled client-side via Firebase Android SDK)', [
            'topic' => $topic,
            'count' => count($tokens),
        ]);

        return ['success' => true, 'unsubscribed' => count($tokens)];
    }

    /**
     * Construct standard FCM HTTP v1 message structure.
     */
    private function buildFcmMessage(?string $token, array $payload, string $channelId, ?string $topic = null): array
    {
        $title = (string) ($payload['title'] ?? config('app.name', 'Estele'));
        $body = (string) ($payload['body'] ?? '');
        $data = $payload['data'] ?? [];

        // Ensure all data items are strings for FCM
        $stringData = [];
        foreach ($data as $k => $v) {
            $stringData[(string) $k] = is_array($v) ? json_encode($v) : (string) $v;
        }
        $stringData['channelId'] = $channelId;
        $stringData['title'] = $title;
        $stringData['body'] = $body;

        $message = [
            'notification' => [
                'title' => $title,
                'body' => $body,
            ],
            'data' => $stringData,
            'android' => [
                'priority' => 'HIGH',
                'notification' => [
                    'channel_id' => $channelId,
                    'sound' => 'default',
                    'icon' => 'notification_icon',
                    'color' => '#AD3D5F',
                ],
            ],
            'apns' => [
                'headers' => [
                    'apns-priority' => '10',
                ],
                'payload' => [
                    'aps' => [
                        'alert' => [
                            'title' => $title,
                            'body' => $body,
                        ],
                        'sound' => 'default',
                        'badge' => isset($payload['badge']) ? (int) $payload['badge'] : 1,
                        'content-available' => 1,
                        'mutable-content' => 1,
                    ],
                ],
            ],
        ];

        if ($token) {
            $message['token'] = $token;
        } elseif ($topic) {
            $message['topic'] = $topic;
        }

        if (! empty($payload['image'])) {
            $message['notification']['image'] = $payload['image'];
            $message['android']['notification']['image'] = $payload['image'];
            $message['apns']['fcm_options'] = ['image' => $payload['image']];
        }

        return $message;
    }

    /**
     * Generate or retrieve cached OAuth2 access token for Google Cloud Service Account.
     */
    private function getOAuth2AccessToken(string $projectId, string $credentialsPath): ?string
    {
        $cacheKey = "fcm_oauth2_token_{$projectId}";
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        $credentials = null;
        if (filled(env('FIREBASE_CREDENTIALS_JSON'))) {
            $credentials = json_decode(env('FIREBASE_CREDENTIALS_JSON'), true);
        } elseif (file_exists($credentialsPath)) {
            $credentials = json_decode(file_get_contents($credentialsPath), true);
        }

        if (! $credentials || empty($credentials['client_email']) || empty($credentials['private_key'])) {
            return null;
        }

        $now = time();
        $jwtHeader = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $jwtClaims = $this->base64UrlEncode(json_encode([
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => $now + 3600,
            'iat' => $now,
        ]));

        $signatureInput = "{$jwtHeader}.{$jwtClaims}";
        $signature = '';
        if (! openssl_sign($signatureInput, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
            Log::warning('openssl_sign failed for Firebase JWT');
            return null;
        }

        $jwt = "{$signatureInput}." . $this->base64UrlEncode($signature);

        try {
            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if ($response->successful()) {
                $token = $response->json('access_token');
                $expiresIn = (int) ($response->json('expires_in', 3600) - 300);
                Cache::put($cacheKey, $token, max($expiresIn, 60));
                return $token;
            }
        } catch (\Throwable $e) {
            Log::warning('OAuth2 token fetch error for FCM', ['error' => $e->getMessage()]);
        }

        return null;
    }

    private function resolveCredentialsPath(): string
    {
        $path = config('services.firebase.credentials', env('FIREBASE_CREDENTIALS', storage_path('app/firebase-credentials.json')));
        if ($path && ! str_starts_with($path, '/')) {
            $path = base_path($path);
        }

        return (string) $path;
    }

    private function base64UrlEncode(string $data): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }
}
