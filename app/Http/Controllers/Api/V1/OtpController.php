<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\UserResource;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OtpController extends ApiController
{
    /**
     * Verify phone number via submitted OTP code.
     */
    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['nullable', 'string', 'max:20'],
            'otp' => ['required', 'string', 'max:10'],
        ]);

        $user = $request->user() ?: $request->user('sanctum');
        if (! $user && ! empty($validated['phone'])) {
            $user = User::where('phone', $validated['phone'])->first();
        }

        if (! $user) {
            return $this->errorResponse('User account not found', 404);
        }

        if ($user->isPhoneVerified()) {
            return $this->successResponse([
                'user' => new UserResource($user),
                'verified' => true,
            ], 'Phone number is already verified');
        }

        $submittedOtp = trim($validated['otp']);

        if (empty($user->phone_otp) || $user->phone_otp !== $submittedOtp) {
            throw ValidationException::withMessages([
                'otp' => ['Invalid verification code. Please check and try again.'],
            ]);
        }

        if ($user->phone_otp_expires_at && $user->phone_otp_expires_at->isPast()) {
            throw ValidationException::withMessages([
                'otp' => ['Verification code has expired. Please request a new code.'],
            ]);
        }

        // Mark verified, update status to pending_approval for farmers, and clear OTP
        $newStatus = $user->hasRole('farmer') ? 'pending_approval' : 'active';
        $user->update([
            'phone_verified_at' => now(),
            'status' => $newStatus,
            'phone_otp' => null,
            'phone_otp_expires_at' => null,
        ]);

        if ($user->hasRole('farmer')) {
            \App\Models\AdminNotification::notify(
                'farmer.pending_approval',
                'New Farmer Awaiting Approval',
                "New farmer awaiting approval: {$user->name}, {$user->phone}, " . ($user->district ?? 'N/A'),
                [
                    'farmer_id' => $user->id,
                    'name' => $user->name,
                    'phone' => $user->phone,
                    'district' => $user->district,
                ],
                null
            );
        }

        return $this->successResponse([
            'user' => new UserResource($user->fresh()),
            'verified' => true,
            'status' => $newStatus,
        ], 'Phone number verified successfully. Account is awaiting administrator approval.');
    }

    /**
     * Resend phone OTP verification code with 60-second rate limiting.
     */
    public function resend(Request $request, SmsService $smsService): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $user = $request->user() ?: $request->user('sanctum');
        if (! $user && ! empty($validated['phone'])) {
            $user = User::where('phone', $validated['phone'])->first();
        }

        if (! $user) {
            return $this->errorResponse('User account not found', 404);
        }

        if ($user->isPhoneVerified()) {
            return $this->errorResponse('Phone number is already verified', 400);
        }

        // Rate limit: 60 seconds between resend attempts
        if ($user->phone_otp_sent_at && $user->phone_otp_sent_at->addSeconds(60)->isFuture()) {
            $remainingSeconds = now()->diffInSeconds($user->phone_otp_sent_at->addSeconds(60));
            return response()->json([
                'success' => false,
                'message' => "Please wait {$remainingSeconds} seconds before requesting a new code.",
                'data' => [
                    'retry_after' => $remainingSeconds,
                ],
            ], 429);
        }

        $otp = sprintf('%06d', mt_rand(100000, 999999));

        $user->update([
            'phone_otp' => $otp,
            'phone_otp_sent_at' => now(),
            'phone_otp_expires_at' => now()->addMinutes(10),
        ]);

        $smsService->sendOtp($user->phone, $otp);

        return $this->successResponse([
            'phone' => $user->phone,
            'sent' => true,
            'debug_otp' => config('app.env') !== 'production' ? $otp : null,
        ], 'Verification code resent successfully via SMS');
    }
}
