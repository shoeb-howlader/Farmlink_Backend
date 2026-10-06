<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Resources\V1\UserResource;
use App\Mail\PasswordResetMail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends ApiController
{
    /**
     * Register a new user with the farmer role.
     */
    public function register(RegisterRequest $request, \App\Services\SmsService $smsService): JsonResponse
    {
        $validated = $request->validated();

        $otp = sprintf('%06d', mt_rand(100000, 999999));

        $user = User::create([
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'phone_verified_at' => null,
            'phone_otp' => $otp,
            'phone_otp_expires_at' => now()->addMinutes(10),
            'phone_otp_sent_at' => now(),
            'status' => 'pending_verification',
            'email' => $validated['email'] ?? null,
            'password' => ! empty($validated['password']) ? Hash::make($validated['password']) : null,
            'district' => $validated['district'] ?? null,
            'gender' => $validated['gender'],
        ]);

        $user->assignRole('farmer');

        $smsService->sendOtp($user->phone, $otp);

        $token = $user->createToken('auth_token')->plainTextToken;

        $user->load(['farms', 'roles']);

        return $this->createdResponse([
            'user' => new UserResource($user),
            'token' => $token,
            'token_type' => 'Bearer',
            'requires_phone_verification' => true,
            'debug_otp' => config('app.env') !== 'production' ? $otp : null,
        ], 'User registered successfully');
    }

    /**
     * Authenticate a user and issue a Sanctum token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = $request->findUser();

        if (! $user || ! $user->password || ! Hash::check($request->input('password'), $user->password)) {
            return $this->errorResponse('Invalid credentials', 401);
        }

        if ($user->is_blacklisted) {
            $reason = ! empty($user->blacklist_reason) ? " Reason: {$user->blacklist_reason}." : '';
            return $this->errorResponse("Your account has been suspended by administration.{$reason} Please contact FarmLink customer support at 01711223344 or support@farmlink.com for assistance.", 403);
        }

        if (! $user->is_active) {
            return $this->errorResponse('Your account has been deactivated. Please contact an administrator.', 403);
        }

        $user->update(['last_login_at' => now()]);

        $token = $user->createToken('auth_token')->plainTextToken;

        $user->load(['farms', 'roles']);

        return $this->successResponse([
            'user' => new UserResource($user),
            'token' => $token,
            'token_type' => 'Bearer',
        ], 'Login successful');
    }

    /**
     * Revoke the current access token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->successResponse(
            null,
            'Logged out successfully'
        );
    }

    /**
     * Return the authenticated user profile with roles and farms.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['farms', 'roles']);

        return $this->successResponse(
            new UserResource($user),
            'Authenticated user profile retrieved successfully'
        );
    }

    /**
     * Update the authenticated user profile.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', \Illuminate\Validation\Rule::unique('users', 'phone')->ignore($user->id)],
            'email' => ['nullable', 'email', 'max:255', \Illuminate\Validation\Rule::unique('users', 'email')->ignore($user->id)],
            'district' => ['nullable', 'string', 'max:100'],
            'gender' => ['required', 'string', 'in:male,female,unspecified'],
            'avatar_url' => ['nullable', 'string', 'max:500'],
        ]);

        $user->update($validated);

        return $this->successResponse(
            new UserResource($user->fresh(['farms', 'roles'])),
            'Profile updated successfully'
        );
    }

    /**
     * Update the authenticated user password.
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The provided current password does not match our records.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return $this->successResponse(null, 'Password changed successfully');
    }

    /**
     * Update the authenticated user profile photo (URL-based legacy).
     */
    public function updatePhoto(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'avatar_url' => ['required', 'string', 'max:500'],
        ]);

        $user->update([
            'avatar_url' => $validated['avatar_url'],
        ]);

        return $this->successResponse(
            new UserResource($user->fresh(['farms', 'roles'])),
            'Profile photo updated successfully'
        );
    }

    /**
     * Upload and update the authenticated user's profile avatar.
     */
    public function updateAvatar(\App\Http\Requests\Api\V1\UploadImageRequest $request, \App\Services\ImageUploadService $uploader): JsonResponse
    {
        $user = $request->user();
        $file = $request->getImageFile();
        $result = $uploader->storeImageWithThumbnail($file, 'avatars');

        $user->update([
            'profile_image_path' => $result['path'],
            'profile_image_thumbnail_path' => $result['thumbnail_path'],
            'avatar_url' => $result['url'],
        ]);

        return $this->successResponse([
            'path' => $result['path'],
            'thumbnail_path' => $result['thumbnail_path'],
            'avatar_url' => $result['url'],
            'avatar_thumbnail_url' => $result['thumbnail_url'],
            'user' => new UserResource($user->fresh(['farms', 'roles'])),
        ], 'Profile avatar updated successfully');
    }

    /**
     * Request a password reset OTP code via phone or email.
     */
    public function forgotPassword(Request $request, \App\Services\SmsService $smsService): JsonResponse
    {
        $validated = $request->validate([
            'login' => ['required', 'string', 'max:255'],
        ]);

        $identifier = trim($validated['login']);
        $user = User::where('phone', $identifier)
            ->orWhere('email', $identifier)
            ->first();

        // Enforce anti-enumeration: If user does not exist, return a generic safe success message
        if (! $user) {
            return $this->successResponse([
                'sent' => true,
                'channel' => filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone',
                'masked_target' => $this->maskIdentifier($identifier),
            ], 'If an account exists for this phone or email, a verification code has been dispatched.');
        }

        // Prevent rapid spamming: 60 seconds cooldown check
        if ($user->phone_otp_sent_at && $user->phone_otp_sent_at->addSeconds(60)->isFuture()) {
            $remaining = now()->diffInSeconds($user->phone_otp_sent_at->addSeconds(60));
            throw ValidationException::withMessages([
                'login' => ["Please wait {$remaining} seconds before requesting another code."],
            ]);
        }

        $otp = sprintf('%06d', mt_rand(100000, 999999));
        $user->update([
            'phone_otp' => $otp,
            'phone_otp_expires_at' => now()->addMinutes(10),
            'phone_otp_sent_at' => now(),
        ]);

        $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL) || (empty($user->phone) && ! empty($user->email));

        if ($user->phone) {
            $smsService->sendOtp($user->phone, $otp);
        }

        if ($user->email && $isEmail) {
            $brokerToken = Password::broker()->createToken($user);
            Mail::to($user->email)->queue(new PasswordResetMail($user, $brokerToken, $otp));
            \Illuminate\Support\Facades\Log::info("[EMAIL DISPATCH] Password reset email queued for {$user->email} with broker token and OTP: {$otp}");
        }

        $targetToMask = $isEmail && $user->email ? $user->email : ($user->phone ?? $identifier);

        return $this->successResponse([
            'sent' => true,
            'channel' => $isEmail ? 'email' : 'phone',
            'masked_target' => $this->maskIdentifier($targetToMask),
            'debug_otp' => config('app.env') !== 'production' ? $otp : null,
        ], 'A 6-digit verification code has been sent.');
    }

    /**
     * Reset password using standard broker token received via email link.
     */
    public function resetPasswordWithToken(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'phone_otp' => null,
                    'phone_otp_expires_at' => null,
                    'phone_otp_sent_at' => null,
                ])->setRememberToken(Str::random(60));
                $user->save();

                $user->tokens()->delete();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        $user = User::where('email', $request->input('email'))->first();
        $token = $user->createToken('auth_token')->plainTextToken;
        $user->load(['farms', 'roles']);

        \App\Models\ActivityLog::log(
            'user.password_reset',
            $user,
            ['action' => 'Password reset via email token link', 'ip' => $request->ip()]
        );

        return $this->successResponse([
            'user' => new UserResource($user),
            'token' => $token,
            'token_type' => 'Bearer',
        ], 'Password has been reset successfully. You are now logged in.');
    }

    /**
     * Reset user password using verified OTP code.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'otp' => ['required', 'string', 'max:10'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $identifier = trim($validated['login']);
        $user = User::where('phone', $identifier)
            ->orWhere('email', $identifier)
            ->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'login' => ['No account found matching this credential.'],
            ]);
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

        // Update password and clear OTP
        $user->update([
            'password' => Hash::make($validated['password']),
            'phone_otp' => null,
            'phone_otp_expires_at' => null,
            'phone_otp_sent_at' => null,
        ]);

        // Security best practice: Revoke all existing sessions/tokens
        $user->tokens()->delete();

        // Create fresh token for seamless login
        $token = $user->createToken('auth_token')->plainTextToken;
        $user->load(['farms', 'roles']);

        // Record security audit log
        \App\Models\ActivityLog::log(
            'user.password_reset',
            $user,
            ['action' => 'Password reset via OTP verification', 'ip' => $request->ip()]
        );

        return $this->successResponse([
            'user' => new UserResource($user),
            'token' => $token,
            'token_type' => 'Bearer',
        ], 'Password has been reset successfully. You are now logged in.');
    }

    /**
     * Mask phone or email for safe display.
     */
    protected function maskIdentifier(string $identifier): string
    {
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $parts = explode('@', $identifier);
            $name = $parts[0];
            $domain = $parts[1] ?? '';
            $maskedName = strlen($name) <= 2
                ? $name[0] . '*'
                : substr($name, 0, 2) . str_repeat('*', max(1, strlen($name) - 3)) . substr($name, -1);
            return $maskedName . '@' . $domain;
        }

        $clean = preg_replace('/[^0-9]/', '', $identifier);
        if (strlen($clean) >= 7) {
            return substr($clean, 0, 3) . '****' . substr($clean, -4);
        }

        return substr($identifier, 0, 2) . '****';
    }
}

