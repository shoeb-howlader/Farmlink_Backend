<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\V1\UserResource;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImpersonationController extends ApiController
{
    /**
     * Start impersonating a user account for troubleshooting and error correction (Admin only).
     */
    public function impersonate(Request $request, User $user): JsonResponse
    {
        $admin = $request->user();

        // 1. Authorization: Only administrators can initiate impersonation
        if (! $admin || ! $admin->hasRole('admin')) {
            return $this->forbiddenResponse('Only administrators are authorized to impersonate user accounts.');
        }

        // 2. Prevent nested/chained impersonation: Cannot impersonate if current session is already impersonated
        $currentToken = $admin->currentAccessToken();
        if ($currentToken && str_starts_with($currentToken->name, 'impersonation:')) {
            return $this->errorResponse('Cannot initiate another impersonation from an active impersonated session.', 400);
        }

        // 3. Prevent self-impersonation
        if ($user->id === $admin->id) {
            return $this->errorResponse('You cannot impersonate your own administrator account.', 400);
        }

        // 4. Prevent privilege escalation: Cannot impersonate other administrators
        if ($user->hasRole('admin')) {
            return $this->forbiddenResponse('Administrator accounts cannot be impersonated.');
        }

        // 5. Require audit reason
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        $reason = trim($validated['reason']);
        $targetRole = $user->roles->pluck('name')->first() ?? 'farmer';

        // 6. Record strict activity audit log
        ActivityLog::log(
            'auth.impersonate_start',
            $user,
            [
                'admin_id' => $admin->id,
                'admin_name' => $admin->name,
                'admin_email' => $admin->email,
                'target_id' => $user->id,
                'target_name' => $user->name,
                'target_phone' => $user->phone,
                'target_role' => $targetRole,
                'reason' => $reason,
                'ip' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
            ],
            $admin
        );

        // 7. Mint scoped Sanctum token with a 2-hour lifespan
        $token = $user->createToken(
            "impersonation:by_{$admin->id}",
            ['*'],
            now()->addHours(2)
        )->plainTextToken;

        // 8. Eager load relations for comprehensive user resource
        $user->load(['farms', 'roles']);

        return $this->successResponse([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
            'impersonator' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
            ],
            'reason' => $reason,
            'expires_at' => now()->addHours(2)->toISOString(),
        ], 'Impersonation session established successfully.');
    }

    /**
     * Stop impersonating and revoke the temporary token.
     */
    public function leave(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();
        $tokenName = $token?->name ?? '';

        // Revoke the temporary impersonation token
        if ($token && str_starts_with($tokenName, 'impersonation:')) {
            $token->delete();
        }

        ActivityLog::log(
            'auth.impersonate_end',
            $user,
            [
                'token_name' => $tokenName,
                'ip' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
            ],
            $user
        );

        return $this->successResponse(null, 'Impersonation session terminated.');
    }
}
