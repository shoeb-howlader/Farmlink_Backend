<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\V1\UserResource;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UserController extends ApiController
{
    /**
     * Authorize that the current request user is an administrator.
     */
    protected function authorizeAdmin(?Request $request = null): void
    {
        $user = $request?->user() ?? auth()->user();
        if (! $user || ! $user->hasRole('admin')) {
            abort(403, 'Unauthorized. Administrative privileges required.');
        }
    }

    /**
     * List all users across the system with role, status, and search filters (Admin only).
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $query = User::with(['roles']);

        // Filter by role
        if ($request->filled('role') && $request->query('role') !== 'all') {
            $role = $request->query('role');
            if (in_array($role, ['admin', 'data_entry_operator', 'veterinary_doctor', 'consultant', 'farmer'])) {
                $query->role($role);
            }
        }

        // Filter by active / restriction status
        if ($request->filled('status') && $request->query('status') !== 'all') {
            $status = $request->query('status');
            if ($status === 'active') {
                $query->where('is_active', true)->where(function ($q) {
                    $q->where('is_blacklisted', false)->orWhereNull('is_blacklisted');
                });
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            } elseif ($status === 'blacklisted') {
                $query->where('is_blacklisted', true);
            }
        }

        // Filter by district
        if ($request->filled('district') && $request->query('district') !== 'all') {
            $query->where('district', $request->query('district'));
        }

        // Search across name, phone, email, district
        if ($request->filled('search')) {
            $search = trim($request->query('search'));
            $lowerSearch = mb_strtolower($search);
            $query->where(function ($q) use ($search, $lowerSearch) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$lowerSearch}%"])
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhereRaw('LOWER(email) LIKE ?', ["%{$lowerSearch}%"])
                    ->orWhereRaw('LOWER(district) LIKE ?', ["%{$lowerSearch}%"]);
            });
        }

        $sortBy = $request->query('sort_by', 'created_at');
        $sortDir = strtolower($request->query('sort_direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        if (in_array($sortBy, ['name', 'phone', 'email', 'district', 'created_at'])) {
            $query->orderBy($sortBy, $sortDir);
        } else {
            $query->latest();
        }

        $perPage = max(1, min((int) $request->query('per_page', 15), 100));
        $paginated = $query->paginate($perPage);

        // Aggregate summary counts
        $summary = Cache::remember('admin_users_directory_summary', 30, function () {
            return [
                'total_users' => User::count(),
                'total_admins' => User::role('admin')->count(),
                'total_farmers' => User::role('farmer')->count(),
                'total_deos' => User::role('data_entry_operator')->count(),
                'total_vets' => User::role('veterinary_doctor')->count(),
                'total_consultants' => User::role('consultant')->count(),
                'total_active' => User::where('is_active', true)->count(),
                'total_inactive' => User::where('is_active', false)->count(),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Users retrieved successfully',
            'data' => UserResource::collection($paginated->getCollection()),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
            'summary' => $summary,
        ]);
    }

    /**
     * Show details of any user (Admin only).
     */
    public function show(Request $request, User $user): JsonResponse
    {
        $this->authorizeAdmin($request);

        $user->load(['roles', 'permissions']);

        return $this->successResponse(
            new UserResource($user),
            'User retrieved successfully'
        );
    }

    /**
     * Update name, email, phone number, role, password, and status of any user (Admin only).
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $this->authorizeAdmin($request);
        $currentUser = $request->user();

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['sometimes', 'required', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($user->id)],
            'email' => ['sometimes', 'nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'district' => ['nullable', 'string', 'max:100'],
            'gender' => ['sometimes', 'nullable', 'string', 'in:male,female,unspecified'],
            'role' => ['sometimes', 'nullable', 'string', Rule::in(['admin', 'data_entry_operator', 'veterinary_doctor', 'consultant', 'farmer'])],
            'password' => ['nullable', 'string', 'min:6'],
            'is_active' => ['sometimes', 'boolean'],
            'avatar_url' => ['nullable', 'string', 'max:500'],
        ]);

        // Safety: Admin cannot deactivate their own account
        if (isset($validated['is_active']) && ! (bool) $validated['is_active'] && $user->id === $currentUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot deactivate your own administrative account.',
            ], 422);
        }

        // Safety: Admin cannot remove the 'admin' role from themselves
        if (isset($validated['role']) && $validated['role'] !== 'admin' && $user->id === $currentUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot remove administrative privileges from your own account.',
            ], 422);
        }

        $oldValues = [
            'name' => $user->name,
            'phone' => $user->phone,
            'email' => $user->email,
            'district' => $user->district,
            'gender' => $user->gender,
            'role' => $user->roles->first()?->name,
            'is_active' => (bool) $user->is_active,
        ];

        $updates = [];
        if (array_key_exists('name', $validated)) {
            $updates['name'] = trim($validated['name']);
        }
        if (array_key_exists('phone', $validated)) {
            $updates['phone'] = trim($validated['phone']);
        }
        if (array_key_exists('email', $validated)) {
            $updates['email'] = ! empty($validated['email']) ? trim($validated['email']) : null;
        }
        if (array_key_exists('district', $validated)) {
            $updates['district'] = ! empty($validated['district']) ? trim($validated['district']) : null;
        }
        if (array_key_exists('gender', $validated)) {
            $updates['gender'] = $validated['gender'] ?? 'unspecified';
        }
        if (array_key_exists('avatar_url', $validated)) {
            $updates['avatar_url'] = $validated['avatar_url'];
        }
        if (array_key_exists('is_active', $validated)) {
            $updates['is_active'] = (bool) $validated['is_active'];
        }

        $passwordUpdated = false;
        if (! empty($validated['password'])) {
            $updates['password'] = Hash::make($validated['password']);
            $passwordUpdated = true;
        }

        if (! empty($updates)) {
            $user->update($updates);
        }

        if (! empty($validated['role'])) {
            $user->syncRoles([$validated['role']]);
        }

        // Invalidate directory and profile caches
        Cache::forget('admin_users_directory_summary');
        Cache::forget('admin_farmers_directory_summary');
        Cache::forget("admin_farmer_profile_{$user->id}");

        $newValues = [
            'name' => $user->name,
            'phone' => $user->phone,
            'email' => $user->email,
            'district' => $user->district,
            'gender' => $user->gender,
            'role' => $user->roles->first()?->name,
            'is_active' => (bool) $user->is_active,
            'password_changed' => $passwordUpdated,
        ];

        ActivityLog::log(
            'update_user',
            $user,
            [
                'user_id' => $user->id,
                'old' => $oldValues,
                'new' => $newValues,
            ],
            $currentUser
        );

        $user->load(['roles', 'permissions']);

        return $this->successResponse(
            new UserResource($user),
            'User updated successfully'
        );
    }

    /**
     * Reset the password of any user directly (Admin only).
     */
    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $this->authorizeAdmin($request);
        $currentUser = $request->user();

        $validated = $request->validate([
            'password' => ['nullable', 'string', 'min:6'],
            'revoke_tokens' => ['nullable', 'boolean'],
        ]);

        // If no password supplied, generate a secure random 10-character password
        $plainPassword = ! empty($validated['password'])
            ? $validated['password']
            : Str::random(10);

        $user->password = Hash::make($plainPassword);
        $user->save();

        // Optionally revoke all active sessions/Sanctum tokens for this user
        $revokeTokens = $request->boolean('revoke_tokens', true);
        if ($revokeTokens) {
            $user->tokens()->delete();
        }

        ActivityLog::log(
            'reset_user_password',
            $user,
            [
                'user_id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'role' => $user->roles->first()?->name,
                'revoke_tokens' => $revokeTokens,
            ],
            $currentUser
        );

        return response()->json([
            'success' => true,
            'message' => "Password for {$user->name} has been reset successfully.",
            'data' => [
                'user_id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'email' => $user->email,
                'new_password' => $plainPassword,
                'tokens_revoked' => $revokeTokens,
            ],
        ]);
    }
}
