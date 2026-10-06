<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Api\V1\UploadImageRequest;
use App\Http\Resources\V1\FarmResource;
use App\Http\Resources\V1\OrderResource;
use App\Models\User;
use App\Services\ImageUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class FarmerController extends ApiController
{
    /**
     * Display a listing of unique farmers with farm counts and total area (Admin only).
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAdmin', \App\Models\Farm::class);

        $query = User::role('farmer')
            ->withCount(['farms', 'orders', 'farmVetRecords', 'farmConsultantRecords'])
            ->withSum('orders', 'total')
            ->withSum('farms', 'total_area');

        // Filter by district
        if ($request->filled('district') && $request->query('district') !== 'all') {
            $query->where('district', $request->query('district'));
        }

        // Account Restriction Status Filter (all, active, cod_blocked, consultations_blocked, blacklisted)
        $restriction = $request->query('restriction');
        if ($restriction === 'blacklisted') {
            $query->where('is_blacklisted', true);
        } elseif ($restriction === 'cod_blocked') {
            $query->where('cod_blocked', true);
        } elseif ($restriction === 'consultations_blocked') {
            $query->where('consultations_blocked', true);
        } elseif ($restriction === 'active') {
            $query->where('is_active', true)->where('is_blacklisted', false);
        } elseif ($restriction === 'inactive') {
            $query->where('is_active', false);
        }

        // Quick boolean filters
        if ($request->boolean('has_farms')) {
            $query->has('farms');
        }

        if ($request->boolean('has_orders')) {
            $query->has('orders');
        }

        if ($request->boolean('has_visits')) {
            $query->where(function ($q) {
                $q->has('farmVetRecords')->orHas('farmConsultantRecords');
            });
        }

        // Search across name, phone, email, or district
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

        switch ($sortBy) {
            case 'orders_count':
            case 'total_orders':
                $query->orderBy('orders_count', $sortDir);
                break;
            case 'orders_sum_total':
            case 'total_purchased':
                $query->orderBy('orders_sum_total', $sortDir);
                break;
            case 'visits_count':
                $query->orderByRaw('(coalesce(farm_vet_records_count, 0) + coalesce(farm_consultant_records_count, 0)) ' . $sortDir);
                break;
            case 'farms_count':
                $query->orderBy('farms_count', $sortDir);
                break;
            case 'total_area':
                $query->orderBy('farms_sum_total_area', $sortDir);
                break;
            case 'name':
                $query->orderBy('name', $sortDir);
                break;
            default:
                $query->latest();
                break;
        }

        $perPage = max(1, min((int) $request->query('per_page', 15), 100));
        $paginated = $query->paginate($perPage);

        // Compute system-wide aggregate summary for farmers directory
        $summary = Cache::remember('admin_farmers_directory_summary', 60, function () {
            $userStats = User::role('farmer')->selectRaw("
                count(*) as total,
                count(case when is_active = true and (is_blacklisted = false or is_blacklisted is null) then 1 end) as active_count,
                count(case when cod_blocked = true then 1 end) as cod_blocked_count,
                count(case when consultations_blocked = true then 1 end) as consultations_blocked_count,
                count(case when is_blacklisted = true then 1 end) as blacklisted_count
            ")->first();

            $totalFarms = \App\Models\Farm::whereHas('user', fn($q) => $q->role('farmer'))->count();
            $totalArea = round((float) \App\Models\Farm::whereHas('user', fn($q) => $q->role('farmer'))->sum('total_area'), 2);
            $totalSpend = round((float) \App\Models\Order::whereHas('user', fn($q) => $q->role('farmer'))->sum('total'), 2);
            $totalVisits = (int) (\App\Models\VetRecord::count() + \App\Models\ConsultantRecord::count());

            return [
                'total_farmers' => (int) ($userStats->total ?? 0),
                'total_active' => (int) ($userStats->active_count ?? 0),
                'total_cod_blocked' => (int) ($userStats->cod_blocked_count ?? 0),
                'total_consultations_blocked' => (int) ($userStats->consultations_blocked_count ?? 0),
                'total_blacklisted' => (int) ($userStats->blacklisted_count ?? 0),
                'total_farms' => $totalFarms,
                'total_area' => $totalArea,
                'total_spend' => $totalSpend,
                'total_visits' => $totalVisits,
            ];
        });

        $data = $paginated->getCollection()->map(function (User $farmer) {
            $avatarUrl = $farmer->profile_image_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($farmer->profile_image_path) : $farmer->avatar_url;
            $thumbUrl = $farmer->profile_image_thumbnail_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($farmer->profile_image_thumbnail_path) : $avatarUrl;

            return [
                'id' => $farmer->id,
                'name' => $farmer->name,
                'phone' => $farmer->phone,
                'email' => $farmer->email,
                'district' => $farmer->district,
                'gender' => $farmer->gender ?? 'unspecified',
                'is_active' => (bool) ($farmer->is_active ?? true),
                'is_blacklisted' => (bool) ($farmer->is_blacklisted ?? false),
                'blacklist_reason' => $farmer->blacklist_reason,
                'cod_blocked' => (bool) ($farmer->cod_blocked ?? false),
                'consultations_blocked' => (bool) ($farmer->consultations_blocked ?? false),
                'refused_cod_orders_count' => (int) ($farmer->refused_cod_orders_count ?? 0),
                'avatar_url' => $avatarUrl,
                'avatar_thumbnail_url' => $thumbUrl,
                'farms_count' => $farmer->farms_count ?? 0,
                'total_area' => round((float) ($farmer->farms_sum_total_area ?? 0), 2),
                'total_orders' => (int) ($farmer->orders_count ?? 0),
                'total_purchased' => round((float) ($farmer->orders_sum_total ?? 0), 2),
                'visits_count' => (int) (($farmer->farm_vet_records_count ?? 0) + ($farmer->farm_consultant_records_count ?? 0)),
                'created_at' => $farmer->created_at?->toISOString(),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Farmers retrieved successfully',
            'data' => $data,
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
     * Store a newly created farmer account (Admin only).
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('viewAdmin', \App\Models\Farm::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone'],
            'district' => ['nullable', 'string', 'max:100'],
            'gender' => ['required', 'string', 'in:male,female,unspecified'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'password' => ['nullable', 'string', 'min:6'],
            'avatar_url' => ['nullable', 'string', 'max:500'],
        ]);

        $plainPassword = $validated['password'] ?? Str::random(10);

        $farmer = User::create([
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'phone_verified_at' => now(),
            'district' => $validated['district'] ?? null,
            'gender' => $validated['gender'],
            'email' => $validated['email'] ?? null,
            'password' => Hash::make($plainPassword),
            'is_active' => true,
            'avatar_url' => $validated['avatar_url'] ?? null,
        ]);

        $farmer->syncRoles(['farmer']);

        \App\Models\ActivityLog::log(
            'create_farmer',
            $farmer,
            ['name' => $farmer->name, 'phone' => $farmer->phone, 'district' => $farmer->district]
        );

        \App\Models\AdminNotification::notify(
            'farmer',
            'New Farmer Registered',
            "Farmer {$farmer->name} ({$farmer->phone}) registered in {$farmer->district}.",
            ['farmer_id' => $farmer->id]
        );

        return $this->createdResponse([
            'id' => $farmer->id,
            'name' => $farmer->name,
            'phone' => $farmer->phone,
            'email' => $farmer->email,
            'district' => $farmer->district,
            'gender' => $farmer->gender ?? 'unspecified',
            'is_active' => true,
            'avatar_url' => $farmer->avatar_url,
            'initial_password' => $plainPassword,
            'created_at' => $farmer->created_at?->toISOString(),
        ], 'Farmer created successfully');
    }

    /**
     * Display farmer details including all farms and order history across farms (Admin only).
     */
    public function show(User $user): JsonResponse
    {
        Gate::authorize('viewAdmin', \App\Models\Farm::class);

        // Ensure user is a farmer
        if (! $user->hasRole('farmer')) {
            return response()->json([
                'success' => false,
                'message' => 'Requested user is not a farmer',
            ], 404);
        }

        $profileData = Cache::remember("admin_farmer_profile_{$user->id}", 60, function () use ($user) {
            $user->load([
                'farms' => fn ($q) => $q->with(['division', 'districtModel', 'upazilaModel', 'unionModel', 'pourashava', 'images'])->withCount(['orders', 'vetRecords', 'consultantRecords'])->latest(),
                'orders' => fn ($q) => $q->with(['items.product', 'items.variant', 'payment'])->latest(),
            ]);

            $totalArea = $user->farms->sum('total_area');
            $avatarUrl = $user->profile_image_path ? Storage::disk('public')->url($user->profile_image_path) : $user->avatar_url;
            $thumbUrl = $user->profile_image_thumbnail_path ? Storage::disk('public')->url($user->profile_image_thumbnail_path) : $avatarUrl;

            $ordersData = $user->orders->map(function ($order) {
                return [
                    'id' => $order->id,
                    'invoice_number' => $order->invoice_number,
                    'status' => $order->status,
                    'channel' => $order->channel ?? 'self_service',
                    'payment_mode' => $order->payment_mode ?? 'cod',
                    'payment_status' => $order->payment_status,
                    'subtotal' => (float) ($order->subtotal ?? $order->total),
                    'delivery_fee' => (float) ($order->delivery_fee ?? 0.00),
                    'discount_amount' => (float) ($order->discount_amount ?? 0.00),
                    'total' => (float) $order->total,
                    'created_at' => $order->created_at?->toISOString(),
                    'items' => $order->items->map(function ($item) {
                        $price = (float) ($item->price_at_purchase ?? $item->price);
                        $product = $item->product;
                        $imageUrl = $product?->image_path ? Storage::disk('public')->url($product->image_path) : $product?->image_url;
                        $thumbUrl = $product?->image_thumbnail_path ? Storage::disk('public')->url($product->image_thumbnail_path) : $imageUrl;
                        return [
                            'id' => $item->id,
                            'order_id' => $item->order_id,
                            'product_id' => $item->product_id,
                            'product_variant_id' => $item->product_variant_id,
                            'quantity' => (int) $item->quantity,
                            'price' => $price,
                            'price_at_purchase' => $price,
                            'total' => round($price * $item->quantity, 2),
                            'product' => $product ? [
                                'id' => $product->id,
                                'name' => $product->name,
                                'price' => (float) $product->price,
                                'image_url' => $imageUrl,
                                'image_thumbnail_url' => $thumbUrl,
                            ] : null,
                        ];
                    })->all(),
                ];
            })->all();

            return [
                'id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'email' => $user->email,
                'district' => $user->district,
                'gender' => $user->gender ?? 'unspecified',
                'is_active' => (bool) ($user->is_active ?? true),
                'is_blacklisted' => (bool) ($user->is_blacklisted ?? false),
                'blacklist_reason' => $user->blacklist_reason,
                'cod_blocked' => (bool) ($user->cod_blocked ?? false),
                'consultations_blocked' => (bool) ($user->consultations_blocked ?? false),
                'refused_cod_orders_count' => (int) ($user->refused_cod_orders_count ?? 0),
                'avatar_url' => $avatarUrl,
                'avatar_thumbnail_url' => $thumbUrl,
                'farms_count' => $user->farms->count(),
                'total_area' => round((float) $totalArea, 2),
                'farms' => FarmResource::collection($user->farms)->resolve(),
                'orders' => $ordersData,
                'created_at' => $user->created_at?->toISOString(),
            ];
        });

        return $this->successResponse($profileData, 'Farmer profile retrieved successfully');
    }

    /**
     * Update account restrictions (Blacklist, COD Block, Consultations Block, Deactivate) for a farmer (Admin only).
     */
    public function updateRestriction(Request $request, User $user): JsonResponse
    {
        Gate::authorize('viewAdmin', \App\Models\Farm::class);

        if (! $user->hasRole('farmer')) {
            return response()->json([
                'success' => false,
                'message' => 'Requested user is not a farmer',
            ], 404);
        }

        $validated = $request->validate([
            'is_blacklisted' => ['nullable', 'boolean'],
            'blacklist_reason' => ['nullable', 'string', 'max:500'],
            'cod_blocked' => ['nullable', 'boolean'],
            'consultations_blocked' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $oldBlacklisted = (bool) $user->is_blacklisted;
        $oldCodBlocked = (bool) $user->cod_blocked;
        $oldConsultationsBlocked = (bool) $user->consultations_blocked;
        $oldIsActive = (bool) $user->is_active;

        if (isset($validated['is_blacklisted'])) {
            $user->is_blacklisted = (bool) $validated['is_blacklisted'];
            if ($user->is_blacklisted) {
                if (! empty($validated['blacklist_reason'])) {
                    $user->blacklist_reason = trim($validated['blacklist_reason']);
                }
                // Synchronized Hard Ban: Blacklisting automatically deactivates account & revokes all active auth tokens
                $user->is_active = false;
                $user->tokens()->delete();
            } else {
                $user->blacklist_reason = null;
                // Restore active status when unbanning unless explicitly set to inactive
                if (! isset($validated['is_active']) || (bool) $validated['is_active'] === true) {
                    $user->is_active = true;
                }
            }
        }

        if (isset($validated['cod_blocked'])) {
            $user->cod_blocked = (bool) $validated['cod_blocked'];
        }

        if (isset($validated['consultations_blocked'])) {
            $user->consultations_blocked = (bool) $validated['consultations_blocked'];
        }

        if (isset($validated['is_active']) && ! $user->is_blacklisted) {
            $user->is_active = (bool) $validated['is_active'];
        }

        $user->save();

        Cache::forget("admin_farmer_profile_{$user->id}");

        \App\Models\ActivityLog::log(
            'farmer.restriction_updated',
            $user,
            [
                'farmer_id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'is_blacklisted' => ['old' => $oldBlacklisted, 'new' => (bool) $user->is_blacklisted],
                'blacklist_reason' => $user->blacklist_reason,
                'cod_blocked' => ['old' => $oldCodBlocked, 'new' => (bool) $user->cod_blocked],
                'consultations_blocked' => ['old' => $oldConsultationsBlocked, 'new' => (bool) $user->consultations_blocked],
                'is_active' => ['old' => $oldIsActive, 'new' => (bool) $user->is_active],
            ],
            $request->user()
        );

        return $this->successResponse([
            'id' => $user->id,
            'name' => $user->name,
            'phone' => $user->phone,
            'is_active' => (bool) $user->is_active,
            'is_blacklisted' => (bool) $user->is_blacklisted,
            'blacklist_reason' => $user->blacklist_reason,
            'cod_blocked' => (bool) $user->cod_blocked,
            'consultations_blocked' => (bool) $user->consultations_blocked,
            'refused_cod_orders_count' => (int) ($user->refused_cod_orders_count ?? 0),
        ], 'Account restrictions updated successfully');
    }

    /**
     * Update farmer details (Admin only).
     */
    public function update(Request $request, User $user): JsonResponse
    {
        Gate::authorize('viewAdmin', \App\Models\Farm::class);

        // Ensure user is a farmer
        if (! $user->hasRole('farmer')) {
            return response()->json([
                'success' => false,
                'message' => 'Requested user is not a farmer',
            ], 404);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['sometimes', 'required', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($user->id)],
            'district' => ['nullable', 'string', 'max:100'],
            'gender' => ['sometimes', 'required', 'string', 'in:male,female,unspecified'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:6'],
            'is_active' => ['sometimes', 'boolean'],
            'avatar_url' => ['nullable', 'string', 'max:500'],
        ]);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        if ($request->boolean('remove_avatar')) {
            if ($user->profile_image_path) {
                Storage::disk('public')->delete($user->profile_image_path);
            }
            if ($user->profile_image_thumbnail_path) {
                Storage::disk('public')->delete($user->profile_image_thumbnail_path);
            }
            $user->update([
                'profile_image_path' => null,
                'profile_image_thumbnail_path' => null,
                'avatar_url' => null,
            ]);
        }

        \App\Models\ActivityLog::log(
            'update_farmer',
            $user,
            ['name' => $user->name, 'phone' => $user->phone, 'district' => $user->district],
            $request->user()
        );

        Cache::forget("admin_farmer_profile_{$user->id}");

        $avatarUrl = $user->profile_image_path ? Storage::disk('public')->url($user->profile_image_path) : $user->avatar_url;
        $thumbUrl = $user->profile_image_thumbnail_path ? Storage::disk('public')->url($user->profile_image_thumbnail_path) : $avatarUrl;

        return $this->successResponse([
            'id' => $user->id,
            'name' => $user->name,
            'phone' => $user->phone,
            'email' => $user->email,
            'district' => $user->district,
            'gender' => $user->gender ?? 'unspecified',
            'is_active' => (bool) ($user->is_active ?? true),
            'avatar_url' => $avatarUrl,
            'avatar_thumbnail_url' => $thumbUrl,
            'created_at' => $user->created_at?->toISOString(),
        ], 'Farmer updated successfully');
    }

    /**
     * Upload and update a farmer's avatar profile picture (Admin only).
     */
    public function uploadAvatar(UploadImageRequest $request, User $user, ImageUploadService $uploader): JsonResponse
    {
        Gate::authorize('viewAdmin', \App\Models\Farm::class);

        if (! $user->hasRole('farmer')) {
            return response()->json([
                'success' => false,
                'message' => 'Requested user is not a farmer',
            ], 404);
        }

        // Delete old custom profile pictures if they exist
        if ($user->profile_image_path) {
            Storage::disk('public')->delete($user->profile_image_path);
        }
        if ($user->profile_image_thumbnail_path) {
            Storage::disk('public')->delete($user->profile_image_thumbnail_path);
        }

        $file = $request->getImageFile();
        $result = $uploader->storeImageWithThumbnail($file, 'avatars');

        $user->update([
            'profile_image_path' => $result['path'],
            'profile_image_thumbnail_path' => $result['thumbnail_path'],
            'avatar_url' => $result['url'],
        ]);

        Cache::forget("admin_farmer_profile_{$user->id}");

        \App\Models\ActivityLog::log(
            'update_farmer_avatar',
            $user,
            ['name' => $user->name, 'path' => $result['path']],
            $request->user()
        );

        return $this->successResponse([
            'id' => $user->id,
            'name' => $user->name,
            'avatar_url' => $result['url'],
            'avatar_thumbnail_url' => $result['thumbnail_url'],
            'profile_image_path' => $result['path'],
            'profile_image_thumbnail_path' => $result['thumbnail_path'],
        ], 'Farmer profile picture updated successfully');
    }

    /**
     * Remove custom profile picture for a farmer (Admin only).
     */
    public function removeAvatar(Request $request, User $user): JsonResponse
    {
        Gate::authorize('viewAdmin', \App\Models\Farm::class);

        if (! $user->hasRole('farmer')) {
            return response()->json([
                'success' => false,
                'message' => 'Requested user is not a farmer',
            ], 404);
        }

        if ($user->profile_image_path) {
            Storage::disk('public')->delete($user->profile_image_path);
        }
        if ($user->profile_image_thumbnail_path) {
            Storage::disk('public')->delete($user->profile_image_thumbnail_path);
        }

        $user->update([
            'profile_image_path' => null,
            'profile_image_thumbnail_path' => null,
            'avatar_url' => null,
        ]);

        Cache::forget("admin_farmer_profile_{$user->id}");

        \App\Models\ActivityLog::log(
            'remove_farmer_avatar',
            $user,
            ['name' => $user->name],
            $request->user()
        );

        return $this->successResponse([
            'id' => $user->id,
            'name' => $user->name,
            'avatar_url' => null,
            'avatar_thumbnail_url' => null,
            'profile_image_path' => null,
            'profile_image_thumbnail_path' => null,
        ], 'Farmer profile picture removed successfully');
    }
}
