<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\PushSubscription;
use App\Services\WebPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushNotificationController extends ApiController
{
    /**
     * Get the public VAPID key for web push subscription.
     */
    public function vapidKey(): JsonResponse
    {
        $publicKey = config('webpush.vapid.public_key');

        return $this->successResponse([
            'public_key' => $publicKey,
        ], 'VAPID public key retrieved successfully');
    }

    /**
     * Register or update a browser push subscription for the authenticated user.
     */
    public function subscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => 'required|url',
            'keys.p256dh' => 'required|string',
            'keys.auth' => 'required|string',
        ]);

        $user = $request->user();

        $subscription = PushSubscription::updateOrCreate(
            ['endpoint' => $validated['endpoint']],
            [
                'user_id' => $user?->id,
                'p256dh' => $validated['keys']['p256dh'],
                'auth' => $validated['keys']['auth'],
                'user_agent' => $request->userAgent(),
            ]
        );

        return $this->successResponse([
            'subscription_id' => $subscription->id,
        ], 'Push subscription registered successfully');
    }

    /**
     * Unsubscribe a browser push subscription endpoint.
     */
    public function unsubscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => 'required|url',
        ]);

        PushSubscription::where('endpoint', $validated['endpoint'])->delete();

        return $this->successResponse(null, 'Push subscription removed successfully');
    }

    /**
     * Send a test push notification to the authenticated user.
     */
    public function test(Request $request): JsonResponse
    {
        $user = $request->user();

        WebPushService::sendToUser(
            $user,
            'FarmLink Web Push Test',
            'Push notifications are working perfectly on your device!',
            '/dashboard'
        );

        return $this->successResponse(null, 'Test push notification dispatched');
    }
}
