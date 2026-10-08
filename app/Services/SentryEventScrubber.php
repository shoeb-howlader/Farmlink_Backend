<?php

namespace App\Services;

use Sentry\Event;
use Sentry\EventHint;
use Sentry\UserDataBag;

class SentryEventScrubber
{
    /**
     * Keys to recursively redact from request bodies, context, and breadcrumbs.
     *
     * @var array<string>
     */
    protected static array $sensitiveKeys = [
        'password',
        'password_confirmation',
        'current_password',
        'token',
        'access_token',
        'remember_token',
        'otp',
        'code',
        'otp_code',
        'card_number',
        'cvv',
        'card_cvv',
        'val_id',
        'bank_tran_id',
        'store_passwd',
        'api_key',
        'secret',
        'private_key',
    ];

    /**
     * Sentry before_send callback.
     */
    public static function scrub(Event $event, ?EventHint $hint): ?Event
    {
        // 1. Scrub User PII: Ensure only user ID and role are present, NEVER email or phone
        $user = auth()->user();
        if ($user) {
            $role = null;
            if (method_exists($user, 'roles')) {
                $role = $user->roles->first()?->name;
            }

            $userBag = UserDataBag::createFromUserIdentifier((string) $user->id);
            if ($role) {
                $userBag->setMetadata('role', $role);
                $userBag->setSegment($role);
            }
            $event->setUser($userBag);
        } else {
            // Strip any default IP address / email if set by Sentry
            $event->setUser(null);
        }

        // 2. Scrub Request data
        $request = $event->getRequest();
        if (! empty($request['data']) && is_array($request['data'])) {
            $request['data'] = self::redactSensitiveData($request['data']);
            $event->setRequest($request);
        }

        // 3. Scrub Extra context data
        $extra = $event->getExtra();
        if (! empty($extra)) {
            $event->setExtra(self::redactSensitiveData($extra));
        }

        return $event;
    }

    /**
     * Recursively mask sensitive values in an array.
     */
    public static function redactSensitiveData(mixed $data): mixed
    {
        if (! is_array($data)) {
            return $data;
        }

        $redacted = [];
        foreach ($data as $key => $value) {
            $normalizedKey = strtolower((string) $key);
            $isSensitive = false;

            foreach (self::$sensitiveKeys as $pattern) {
                if ($normalizedKey === $pattern || str_contains($normalizedKey, $pattern)) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $redacted[$key] = '[FILTERED]';
            } elseif (is_array($value)) {
                $redacted[$key] = self::redactSensitiveData($value);
            } else {
                $redacted[$key] = $value;
            }
        }

        return $redacted;
    }
}
