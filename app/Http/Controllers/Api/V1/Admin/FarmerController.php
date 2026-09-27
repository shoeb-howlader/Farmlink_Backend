<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\V1\FarmResource;
use App\Http\Resources\V1\OrderResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
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

        $perPage = max(1, min((int) $request->query('per_page', 10), 100));
        $paginated = $query->paginate($perPage);

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

        $user->load([
            'farms' => fn ($q) => $q->latest(),
            'orders' => fn ($q) => $q->with(['items.product'])->latest(),
        ]);

        $totalArea = $user->farms->sum('total_area');
        $avatarUrl = $user->profile_image_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($user->profile_image_path) : $user->avatar_url;
        $thumbUrl = $user->profile_image_thumbnail_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($user->profile_image_thumbnail_path) : $avatarUrl;

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
            'farms_count' => $user->farms->count(),
            'total_area' => round((float) $totalArea, 2),
            'farms' => FarmResource::collection($user->farms),
            'orders' => OrderResource::collection($user->orders),
            'created_at' => $user->created_at?->toISOString(),
        ], 'Farmer profile retrieved successfully');
    }
}
