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
            if (! $user->isPhoneVerified()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Phone verification required. Please verify your phone number via OTP before placing orders or submitting service requests.',
                    'code' => 'PHONE_NOT_VERIFIED',
                    'errors' => [
                        'phone' => ['Your phone number must be verified to perform this action.'],
                    ],
                ], 403);
            }

            if (! $user->isApproved()) {
                $status = $user->status ?? 'pending_approval';
                $message = $status === 'rejected'
                    ? ($user->rejection_reason ? "Your farmer registration was rejected: {$user->rejection_reason}" : 'Your farmer registration was rejected. Please contact administrator.')
                    : 'Account approval pending. An administrator must approve your account before you can place orders or submit service requests.';
                $code = $status === 'rejected' ? 'FARMER_REJECTED' : 'FARMER_PENDING_APPROVAL';

                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'code' => $code,
                    'errors' => [
                        'status' => [$message],
                    ],
                ], 403);
            }
        }

        return $next($request);
    }
}
