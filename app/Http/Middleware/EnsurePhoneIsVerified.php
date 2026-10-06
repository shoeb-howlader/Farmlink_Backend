<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePhoneIsVerified
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->hasRole('farmer')) {
            if (! $user->isPhoneVerified() || $user->status === 'pending_verification') {
                return response()->json([
                    'success' => false,
                    'message' => 'Phone verification required. Please verify your phone number via OTP before placing orders or submitting service requests.',
                    'code' => 'PHONE_NOT_VERIFIED',
                    'errors' => [
                        'phone' => ['Your phone number must be verified to perform this action.'],
                    ],
                ], 403);
            }
        }

        return $next($request);
    }
}
