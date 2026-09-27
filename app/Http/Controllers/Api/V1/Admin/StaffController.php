<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StaffController extends ApiController
{
    /**
     * @var list<string>
     */
    protected array $staffRoles = [
        'data_entry_operator',
        'veterinary_doctor',
        'consultant',
    ];

    /**
     * Display a listing of staff members, filterable by role (Admin only).
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAdmin', Order::class);

        $roleFilter = $request->query('role');
        $validRoles = in_array($roleFilter, $this->staffRoles) ? [$roleFilter] : $this->staffRoles;

        $query = User::role($validRoles);

        if ($request->filled('district') && $request->query('district') !== 'all') {
            $query->where('district', $request->query('district'));
        }

        if ($request->filled('status') && $request->query('status') !== 'all') {
            $query->where('is_active', $request->query('status') === 'active');
        }

        if ($request->filled('search')) {
            $search = trim($request->query('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('district', 'like', "%{$search}%");
            });
        }

        $perPage = max(1, min((int) $request->query('per_page', 15), 100));
        $paginated = $query->with('roles')->latest()->paginate($perPage);

        $data = $paginated->getCollection()->map(function (User $user) {
            $avatarUrl = $user->profile_image_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($user->profile_image_path) : $user->avatar_url;
            $thumbUrl = $user->profile_image_thumbnail_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($user->profile_image_thumbnail_path) : $avatarUrl;

            return [
                'id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'email' => $user->email,
                'district' => $user->district,
                'gender' => $user->gender ?? 'unspecified',
                'role' => $user->roles->first()?->name ?? 'staff',
                'roles' => $user->roles->pluck('name'),
                'is_active' => (bool) ($user->is_active ?? true),
                'avatar_url' => $avatarUrl,
                'avatar_thumbnail_url' => $thumbUrl,
                'last_login_at' => $user->last_login_at?->toISOString(),
                'created_at' => $user->created_at?->toISOString(),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Staff members retrieved successfully',
            'data' => $data,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    /**
     * Display details and activity summary of a specific staff member (Admin only).
     */
    public function show(User $staff): JsonResponse
    {
        Gate::authorize('viewAdmin', Order::class);

        $staff->load('roles');
        $vetCount = $staff->vetRecords()->count();
        $consultantCount = $staff->consultantRecords()->count();

        $avatarUrl = $staff->profile_image_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($staff->profile_image_path) : $staff->avatar_url;
        $thumbUrl = $staff->profile_image_thumbnail_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($staff->profile_image_thumbnail_path) : $avatarUrl;

        return $this->successResponse([
            'id' => $staff->id,
            'name' => $staff->name,
            'phone' => $staff->phone,
            'email' => $staff->email,
            'district' => $staff->district,
            'gender' => $staff->gender ?? 'unspecified',
            'role' => $staff->roles->first()?->name ?? 'staff',
            'roles' => $staff->roles->pluck('name'),
            'is_active' => (bool) ($staff->is_active ?? true),
            'avatar_url' => $avatarUrl,
            'avatar_thumbnail_url' => $thumbUrl,
            'total_visits' => $vetCount + $consultantCount,
            'vet_visits_count' => $vetCount,
            'consultant_visits_count' => $consultantCount,
            'last_login_at' => $staff->last_login_at?->toISOString(),
            'created_at' => $staff->created_at?->toISOString(),
        ], 'Staff details retrieved successfully');
    }

    /**
     * Store a newly created staff member (Admin only).
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('viewAdmin', Order::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone'],
            'role' => ['required', 'string', Rule::in($this->staffRoles)],
            'district' => ['nullable', 'string', 'max:100'],
            'gender' => ['required', 'string', 'in:male,female,unspecified'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'password' => ['nullable', 'string', 'min:6'],
            'avatar_url' => ['nullable', 'string', 'max:500'],
        ]);

        $plainPassword = $validated['password'] ?? Str::random(10);

        $staff = User::create([
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'district' => $validated['district'] ?? null,
            'gender' => $validated['gender'],
            'email' => $validated['email'] ?? null,
            'password' => Hash::make($plainPassword),
            'is_active' => true,
            'avatar_url' => $validated['avatar_url'] ?? null,
        ]);

        $staff->syncRoles([$validated['role']]);

        ActivityLog::log(
            'create_staff',
            $staff,
            ['name' => $staff->name, 'role' => $validated['role'], 'phone' => $staff->phone]
        );

        return $this->createdResponse([
            'id' => $staff->id,
            'name' => $staff->name,
            'phone' => $staff->phone,
            'email' => $staff->email,
            'district' => $staff->district,
            'gender' => $staff->gender ?? 'unspecified',
            'role' => $validated['role'],
            'is_active' => true,
            'avatar_url' => $staff->avatar_url,
            'initial_password' => $plainPassword,
            'created_at' => $staff->created_at?->toISOString(),
        ], 'Staff member created successfully');
    }

    /**
     * Update an existing staff member (Admin only).
     */
    public function update(Request $request, User $staff): JsonResponse
    {
        Gate::authorize('viewAdmin', Order::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($staff->id)],
            'role' => ['required', 'string', Rule::in($this->staffRoles)],
            'district' => ['nullable', 'string', 'max:100'],
            'gender' => ['required', 'string', 'in:male,female,unspecified'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($staff->id)],
            'password' => ['nullable', 'string', 'min:6'],
            'avatar_url' => ['nullable', 'string', 'max:500'],
        ]);

        $oldRole = $staff->roles->first()?->name;
        $updates = [
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'district' => $validated['district'] ?? null,
            'email' => $validated['email'] ?? null,
        ];

        if (array_key_exists('gender', $validated)) {
            $updates['gender'] = $validated['gender'] ?? 'unspecified';
        }

        if (! empty($validated['password'])) {
            $updates['password'] = Hash::make($validated['password']);
        }

        if (array_key_exists('avatar_url', $validated)) {
            $updates['avatar_url'] = $validated['avatar_url'];
        }

        $staff->update($updates);
        $staff->syncRoles([$validated['role']]);

        ActivityLog::log(
            'update_staff',
            $staff,
            [
                'name' => $staff->name,
                'role' => $validated['role'],
                'previous_role' => $oldRole,
            ]
        );

        $avatarUrl = $staff->profile_image_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($staff->profile_image_path) : $staff->avatar_url;
        $thumbUrl = $staff->profile_image_thumbnail_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($staff->profile_image_thumbnail_path) : $avatarUrl;

        return $this->successResponse([
            'id' => $staff->id,
            'name' => $staff->name,
            'phone' => $staff->phone,
            'email' => $staff->email,
            'district' => $staff->district,
            'gender' => $staff->gender ?? 'unspecified',
            'role' => $validated['role'],
            'is_active' => (bool) ($staff->is_active ?? true),
            'avatar_url' => $avatarUrl,
            'avatar_thumbnail_url' => $thumbUrl,
            'updated_at' => $staff->updated_at?->toISOString(),
        ], 'Staff member updated successfully');
    }

    /**
     * Upload and update a user's avatar (Admin only).
     */
    public function updateAvatar(\App\Http\Requests\Api\V1\UploadImageRequest $request, User $user, \App\Services\ImageUploadService $uploader): JsonResponse
    {
        Gate::authorize('viewAdmin', Order::class);

        $file = $request->getImageFile();
        $result = $uploader->storeImageWithThumbnail($file, 'avatars');

        $user->update([
            'profile_image_path' => $result['path'],
            'profile_image_thumbnail_path' => $result['thumbnail_path'],
            'avatar_url' => $result['url'],
        ]);

        ActivityLog::log(
            'update_avatar',
            $user,
            ['name' => $user->name, 'path' => $result['path']]
        );

        return $this->successResponse([
            'path' => $result['path'],
            'thumbnail_path' => $result['thumbnail_path'],
            'avatar_url' => $result['url'],
            'avatar_thumbnail_url' => $result['thumbnail_url'],
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'avatar_url' => $result['url'],
                'avatar_thumbnail_url' => $result['thumbnail_url'],
            ],
        ], 'User avatar updated successfully');
    }

    /**
     * Toggle or update staff active status.
     */
    public function updateStatus(Request $request, User $staff): JsonResponse
    {
        Gate::authorize('viewAdmin', Order::class);

        if (auth()->id() === $staff->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot deactivate your own administrative account.',
            ], 422);
        }

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $isActive = (bool) $validated['is_active'];
        $staff->update(['is_active' => $isActive]);

        ActivityLog::log(
            $isActive ? 'activate_staff' : 'deactivate_staff',
            $staff,
            ['name' => $staff->name, 'email' => $staff->email]
        );

        return $this->successResponse([
            'id' => $staff->id,
            'is_active' => $isActive,
        ], $isActive ? 'Staff member activated successfully' : 'Staff member deactivated successfully');
    }

    /**
     * Delete staff member if no attached records exist.
     */
    public function destroy(User $staff): JsonResponse
    {
        Gate::authorize('viewAdmin', Order::class);

        if (auth()->id() === $staff->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete your own administrative account.',
            ], 422);
        }

        $hasVetRecords = $staff->vetRecords()->exists();
        $hasConsultantRecords = $staff->consultantRecords()->exists();
        $hasFarms = $staff->farms()->exists();
        $hasOrders = $staff->orders()->exists();

        if ($hasVetRecords || $hasConsultantRecords || $hasFarms || $hasOrders) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete staff member with existing historical records. Please deactivate their account instead.',
                'attached_records' => [
                    'vet_records' => $hasVetRecords,
                    'consultant_records' => $hasConsultantRecords,
                    'farms' => $hasFarms,
                    'orders' => $hasOrders,
                ],
            ], 422);
        }

        $staffData = ['name' => $staff->name, 'email' => $staff->email, 'role' => $staff->roles->first()?->name];

        ActivityLog::log(
            'delete_staff',
            $staff,
            $staffData
        );

        $staff->delete();

        return $this->successResponse(null, 'Staff member deleted successfully');
    }
}
