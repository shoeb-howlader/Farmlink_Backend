<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
}

