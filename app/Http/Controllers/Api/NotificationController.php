<?php

namespace App\Http\Controllers\Api;

use App\Http\Api\ApiResponses;
use App\Models\DeviceToken;
use App\Models\NotificationSchedule;
use App\Models\Order;
use App\Services\PushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class NotificationController
{
    use ApiResponses;

    /**
     * POST /api/notifications/register-token
     * Register an FCM push token. Supports both guest and authenticated users.
     */
    public function registerToken(Request $request): JsonResponse
    {
        $token = $request->input('push_token')
            ?: ($request->input('device_token')
            ?: ($request->input('token')
            ?: $request->input('expo_push_token')));

        if (empty($token) || ! is_string($token)) {
            return response()->json([
                'message' => 'The push token field is required.',
                'errors' => [
                    'push_token' => ['The push token field is required.'],
                    'device_token' => ['The device token field is required.'],
                    'token' => ['The token field is required.'],
                ],
            ], 422);
        }

        $validated = $request->validate([
            'push_token' => ['nullable', 'string', 'max:4096'],
            'device_token' => ['nullable', 'string', 'max:4096'],
            'token' => ['nullable', 'string', 'max:4096'],
            'platform' => ['nullable', 'string', 'in:android,ios'],
            'device_id' => ['nullable', 'string', 'max:255'],
            'provider' => ['nullable', 'string', 'max:20'],
            'app_version' => ['nullable', 'string', 'max:50'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $authUser = Auth::guard('api-token')->user() ?: $request->user();
        $userId = $authUser?->id; // Security: Strictly resolve user from auth token; never trust client-supplied user_id without auth
        $deviceId = $validated['device_id'] ?? null;
        $platform = $validated['platform'] ?? 'android';
        $provider = $validated['provider'] ?? 'fcm';

        // Check if token already exists by token or by device_id
        $deviceToken = DeviceToken::where(function ($q) use ($token) {
            $q->where('push_token', $token)
              ->orWhere('device_token', $token)
              ->orWhere('token', $token)
              ->orWhere('expo_push_token', $token);
        })->first();

        if (! $deviceToken && ! empty($deviceId)) {
            $deviceToken = DeviceToken::where('device_id', $deviceId)->first();
        }

        if ($deviceToken) {
            $deviceToken->update([
                'push_token' => $token,
                'device_token' => $token,
                'token' => $token,
                'expo_push_token' => $token,
                'user_id' => $userId, // If guest, sets to null per security rule
                'device_id' => $deviceId ?? $deviceToken->device_id,
                'platform' => $platform,
                'provider' => $provider,
                'app_version' => $validated['app_version'] ?? $deviceToken->app_version,
                'last_used_at' => now(),
            ]);
        } else {
            $deviceToken = DeviceToken::create([
                'push_token' => $token,
                'device_token' => $token,
                'token' => $token,
                'expo_push_token' => $token,
                'user_id' => $userId,
                'device_id' => $deviceId,
                'platform' => $platform,
                'provider' => $provider,
                'app_version' => $validated['app_version'] ?? null,
                'last_used_at' => now(),
            ]);
        }

        // Schedule management
        $segment = 'guest';
        if ($userId) {
            $user = $authUser ?: \App\Models\User::find($userId);
            $segment = $user ? $user->pushSegment() : 'registered';
            $days = $segment === 'purchased' ? 90 : 30;

            NotificationSchedule::updateOrCreate(
                ['user_id' => $userId, 'notification_type' => 'lifecycle_promo'],
                [
                    'device_id' => $deviceId,
                    'device_token_id' => $token,
                    'segment' => $segment,
                    'next_send_at' => now()->addDays($days),
                    'next_eligible_at' => now()->addDays($days),
                ]
            );
        } else {
            $key = $deviceId
                ? ['device_id' => $deviceId, 'notification_type' => 'lifecycle_promo', 'user_id' => null]
                : ['device_token_id' => $token, 'notification_type' => 'lifecycle_promo', 'user_id' => null];

            NotificationSchedule::updateOrCreate(
                $key,
                [
                    'device_id' => $deviceId,
                    'device_token_id' => $token,
                    'segment' => 'guest',
                    'next_send_at' => now()->addDays(7),
                    'next_eligible_at' => now()->addDays(7),
                ]
            );
        }

        return response()->json([
            'success' => true,
            'registered' => true,
            'segment' => $segment,
            'message' => 'Device token registered successfully',
            'data' => [
                'id' => $deviceToken->id,
                'registered' => true,
                'segment' => $segment,
                'push_token' => $token,
                'device_token' => $token,
                'token' => $token,
                'device_id' => $deviceToken->device_id,
                'user_id' => $deviceToken->user_id,
                'platform' => $deviceToken->platform,
                'provider' => $deviceToken->provider,
                'app_version' => $deviceToken->app_version,
                'registered_at' => $deviceToken->created_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * DELETE /api/notifications/unregister-token
     * Also handles POST /api/notifications/unregister-token
     */
    public function unregisterToken(Request $request): JsonResponse
    {
        $token = $request->input('push_token')
            ?: ($request->input('device_token')
            ?: ($request->input('token')
            ?: $request->input('expo_push_token')));

        $deviceId = $request->input('device_id');

        if (empty($token) && empty($deviceId)) {
            return response()->json([
                'success' => false,
                'message' => 'The device token or device ID is required.',
                'errors' => [
                    'push_token' => ['The push token field is required.'],
                    'token' => ['The token field is required.'],
                    'device_token' => ['The device token field is required.'],
                ],
            ], 422);
        }

        $query = DeviceToken::query();
        if ($token) {
            $query->where(function ($q) use ($token) {
                $q->where('push_token', $token)
                  ->orWhere('device_token', $token)
                  ->orWhere('token', $token)
                  ->orWhere('expo_push_token', $token);
            });
        } elseif ($deviceId) {
            $query->where('device_id', $deviceId);
        }

        $query->delete();

        if ($token) {
            NotificationSchedule::where('device_token_id', $token)
                ->whereNull('user_id')
                ->delete();
        }

        return response()->json([
            'success' => true,
            'unregistered' => true,
            'data' => [
                'unregistered' => true,
            ],
            'message' => 'Device token removed successfully',
        ]);
    }

    /**
     * POST /api/notifications/link-token
     * Link guest FCM token to authenticated user.
     */
    public function linkToken(Request $request): JsonResponse
    {
        $user = $request->user();

        $token = $request->input('push_token')
            ?: ($request->input('device_token')
            ?: ($request->input('token')
            ?: $request->input('expo_push_token')));

        if (empty($token) || ! is_string($token)) {
            return response()->json([
                'success' => false,
                'message' => 'The device token field is required.',
                'errors' => [
                    'push_token' => ['The push token field is required.'],
                    'device_token' => ['The device token field is required.'],
                    'token' => ['The token field is required.'],
                ],
            ], 422);
        }

        $deviceId = $request->input('device_id');
        $platform = $request->input('platform', 'android');

        $deviceToken = DeviceToken::where(function ($q) use ($token) {
            $q->where('push_token', $token)
              ->orWhere('device_token', $token)
              ->orWhere('token', $token)
              ->orWhere('expo_push_token', $token);
        })->first();

        if ($deviceToken) {
            $deviceToken->update([
                'user_id' => $user->id,
                'push_token' => $token,
                'device_token' => $token,
                'token' => $token,
                'expo_push_token' => $token,
                'device_id' => $deviceId ?: $deviceToken->device_id,
                'platform' => $platform ?: $deviceToken->platform,
                'last_used_at' => now(),
            ]);
        } else {
            $deviceToken = DeviceToken::create([
                'user_id' => $user->id,
                'push_token' => $token,
                'device_token' => $token,
                'token' => $token,
                'expo_push_token' => $token,
                'device_id' => $deviceId,
                'platform' => $platform,
                'provider' => 'fcm',
                'last_used_at' => now(),
            ]);
        }

        // Clean up any guest schedule that used this token or device ID
        NotificationSchedule::where(function ($q) use ($token, $deviceId) {
            $q->where('device_token_id', $token);
            if ($deviceId) {
                $q->orWhere('device_id', $deviceId);
            }
        })->whereNull('user_id')->delete();

        // Determine user segment
        $segment = $user->pushSegment();
        $days = $segment === 'purchased' ? 90 : 30;

        NotificationSchedule::updateOrCreate(
            ['user_id' => $user->id, 'notification_type' => 'lifecycle_promo'],
            [
                'device_id' => $deviceId,
                'device_token_id' => $token,
                'segment' => $segment,
                'next_send_at' => now()->addDays($days),
                'next_eligible_at' => now()->addDays($days),
            ]
        );

        return response()->json([
            'success' => true,
            'linked' => true,
            'segment' => $segment,
            'message' => 'Token linked to user successfully',
            'data' => [
                'id' => $deviceToken->id,
                'linked' => true,
                'user_id' => $user->id,
                'push_token' => $token,
                'device_token' => $token,
                'token' => $token,
                'segment' => $segment,
            ],
        ]);
    }

    /**
     * GET /api/notifications
     * Return paginated in-app notifications for authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 20);
        $perPage = max(1, min(100, $perPage));

        $query = $request->user()->notifications();

        $category = $request->input('category');
        if (! empty($category) && $category !== 'all') {
            $query->where(function ($q) use ($category) {
                $q->where('data->category', $category)
                  ->orWhere('data->data->category', $category)
                  ->orWhere('data->type', $category);
            });
        }

        $paginator = $query->latest()->paginate($perPage);

        $items = $paginator->getCollection()->map(function ($n) {
            $raw = is_array($n->data) ? $n->data : json_decode($n->data, true);
            $innerData = $raw['data'] ?? $raw;
            $cat = $raw['category'] ?? ($innerData['category'] ?? 'general');

            return [
                'id' => (string) $n->id,
                'type' => $cat,
                'title' => $raw['title'] ?? '',
                'body' => $raw['body'] ?? '',
                'category' => $cat,
                'channel_id' => $raw['channelId'] ?? ($innerData['channelId'] ?? 'estele_general'),
                'image' => $raw['image'] ?? null,
                'action' => $raw['action'] ?? null,
                'action_id' => $raw['action_id'] ?? null,
                'data' => $innerData,
                'read' => $n->read_at !== null,
                'is_read' => $n->read_at !== null,
                'read_at' => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ]);
    }

    /**
     * GET /api/notifications/unread-count
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $count = $request->user()->unreadNotifications()->count();

        return response()->json([
            'success' => true,
            'count' => $count,
            'data' => [
                'count' => $count,
                'unread_count' => $count,
            ],
        ]);
    }

    /**
     * POST /api/notifications/{id}/read
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->where('id', $id)->first();

        if (! $notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found',
            ], 404);
        }

        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'read' => true,
            'message' => 'Notification marked as read',
            'data' => ['read' => true],
        ]);
    }

    /**
     * Alias for markAsRead (POST /api/notifications/{id}/read)
     */
    public function markRead(Request $request, string $id): JsonResponse
    {
        return $this->markAsRead($request, $id);
    }

    /**
     * POST /api/notifications/read-all
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json([
            'success' => true,
            'read' => true,
            'message' => 'All notifications marked as read',
            'data' => ['read' => true],
        ]);
    }

    /**
     * Alias for markAllAsRead (POST /api/notifications/read-all)
     */
    public function markAllRead(Request $request): JsonResponse
    {
        return $this->markAllAsRead($request);
    }

    /**
     * POST /api/notifications/test-send
     * Send a test push notification to a device token or current authenticated user's registered devices.
     */
    public function testSend(Request $request, PushNotificationService $pushService): JsonResponse
    {
        $token = $request->input('push_token')
            ?: ($request->input('device_token')
            ?: ($request->input('token')
            ?: $request->input('expo_push_token')));

        if (! $token) {
            $user = Auth::guard('api-token')->user() ?: $request->user();
            if ($user) {
                $token = $user->deviceTokens()
                    ->whereNotNull('device_token')
                    ->where('device_token', '!=', '')
                    ->latest()
                    ->value('device_token');
            }
        }

        if (! $token) {
            return response()->json([
                'success' => false,
                'message' => 'No device token found. Please register a push token first or provide push_token in the request body.',
            ], 422);
        }

        $title = $request->input('title', 'Estele Notification ✨');
        $body = $request->input('body', 'Test notification sent at ' . now()->format('h:i A'));
        $channelId = $request->input('channelId', 'estele_general');

        $payload = [
            'title' => $title,
            'body' => $body,
            'channelId' => $channelId,
            'data' => [
                'type' => 'test',
                'action' => 'test',
                'notification_id' => 'test-' . time(),
                'timestamp' => now()->toIso8601String(),
            ],
        ];

        $result = $pushService->send($token, $payload);

        return response()->json([
            'success' => $result['success'] ?? false,
            'simulated' => $result['simulated'] ?? false,
            'message' => ($result['simulated'] ?? false)
                ? 'Test push was simulated (Firebase credentials not yet configured on server).'
                : 'Test push notification dispatched successfully.',
            'results' => $result,
        ]);
    }
}
