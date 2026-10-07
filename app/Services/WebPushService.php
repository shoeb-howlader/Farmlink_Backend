<?php

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class WebPushService
{
    /**
     * Send web push notification to a user's registered devices.
     */
    public static function sendToUser(User|int $user, string $title, string $message, ?string $url = null, array $extraData = []): void
    {
        $userId = $user instanceof User ? $user->id : $user;
        $subscriptions = PushSubscription::where('user_id', $userId)->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        $vapidConfig = config('webpush.vapid');
        if (empty($vapidConfig['public_key']) || empty($vapidConfig['private_key'])) {
            Log::warning('WebPushService: VAPID keys not configured.');
            return;
        }

        $auth = [
            'VAPID' => [
                'subject' => $vapidConfig['subject'] ?? 'mailto:admin@farmlinkcare.com',
                'publicKey' => $vapidConfig['public_key'],
                'privateKey' => $vapidConfig['private_key'],
            ],
        ];

        try {
            $webPush = new WebPush($auth);
            $payload = json_encode([
                'title' => $title,
                'message' => $message,
                'body' => $message,
                'url' => $url ?: '/',
                'data' => array_merge(['url' => $url ?: '/'], $extraData),
                'icon' => '/pwa-192x192.png',
                'badge' => '/pwa-64x64.png',
            ]);

            foreach ($subscriptions as $sub) {
                $subscription = Subscription::create([
                    'endpoint' => $sub->endpoint,
                    'keys' => [
                        'p256dh' => $sub->p256dh,
                        'auth' => $sub->auth,
                    ],
                ]);

                $webPush->queueNotification($subscription, $payload);
            }

            foreach ($webPush->flush() as $report) {
                $endpoint = $report->getRequest()->getUri()->__toString();
                if (!$report->isSuccess()) {
                    Log::info("WebPush failed for {$endpoint}: " . $report->getReason());
                    if ($report->isSubscriptionExpired()) {
                        PushSubscription::where('endpoint', $endpoint)->delete();
                        Log::info("Deleted expired push subscription for {$endpoint}");
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('WebPushService error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
