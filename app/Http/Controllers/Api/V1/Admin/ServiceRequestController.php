<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Api\V1\AssignServiceRequestRequest;
use App\Http\Resources\V1\ServiceRequestResource;
use App\Models\ActivityLog;
use App\Models\ServiceRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ServiceRequestController extends ApiController
{
    /**
     * Display a listing of service requests for admin management.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ServiceRequest::with(['farm', 'farmer', 'assignedPractitioner', 'fulfilledRecord', 'parentRecord'])
            ->urgentFirst();

        if ($request->filled('type') && $request->query('type') !== 'all') {
            $query->where('type', $request->query('type'));
        }

        if ($request->filled('status') && $request->query('status') !== 'all') {
            $status = $request->query('status');
            if (str_contains($status, ',')) {
                $query->whereIn('status', array_map('trim', explode(',', $status)));
            } elseif ($status === 'open' || $status === 'active') {
                $query->whereIn('status', ['pending', 'assigned', 'in_progress']);
            } else {
                $query->where('status', $status);
            }
        }

        if ($request->filled('urgency') && $request->query('urgency') !== 'all') {
            $query->where('urgency', $request->query('urgency'));
        }

        if ($request->filled('source_channel') && $request->query('source_channel') !== 'all') {
            $query->where('source_channel', $request->query('source_channel'));
        }

        if ($request->filled('district') && ! in_array($request->query('district'), ['all', 'All Districts'])) {
            $query->whereHas('farm', function ($q) use ($request) {
                $q->where('district', $request->query('district'));
            });
        }

        if ($request->boolean('overdue_only')) {
            $query->where('status', 'pending')
                ->where(function ($q) {
                    $q->where(function ($uq) {
                        $uq->where('urgency', 'urgent')
                            ->where('created_at', '<=', now()->subHours(ServiceRequest::SLA_URGENT_PENDING_HOURS));
                    })->orWhere(function ($nq) {
                        $nq->where('urgency', '!=', 'urgent')
                            ->where('created_at', '<=', now()->subHours(ServiceRequest::SLA_NORMAL_PENDING_HOURS));
                    });
                });
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhereHas('farmer', function ($fq) use ($search) {
                        $fq->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    })
                    ->orWhereHas('farm', function ($fq) use ($search) {
                        $fq->where('farm_name', 'like', "%{$search}%");
                    });
            });
        }

        $requests = $query->paginate($request->integer('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => 'Service requests retrieved successfully',
            'data' => ServiceRequestResource::collection($requests->items()),
            'meta' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
            ],
        ]);
    }

    /**
     * Display service request details (Admin & DEO).
     */
    public function show(ServiceRequest $serviceRequest): JsonResponse
    {
        $serviceRequest->loadMissing([
            'farm',
            'farmer',
            'assignedPractitioner',
            'fulfilledRecord',
            'parentRecord',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Service request details retrieved successfully',
            'data' => new ServiceRequestResource($serviceRequest),
        ]);
    }

    /**
     * Create a service request on behalf of a farmer (phone/chat/walk-in intake).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'farmer_id' => ['required', 'integer', 'exists:users,id'],
            'farm_id' => ['required', 'integer', 'exists:farms,id'],
            'type' => ['required', 'string', 'in:vet,consultant'],
            'description' => ['required', 'string', 'min:5'],
            'urgency' => ['required', 'string', 'in:normal,urgent'],
            'source_channel' => ['required', 'string', 'in:phone_call,chat,walk_in,admin_created'],
            'photo' => ['nullable', 'file', 'image', 'max:5120'],
        ]);

        $farm = \App\Models\Farm::findOrFail($validated['farm_id']);
        if ($farm->user_id !== (int) $validated['farmer_id']) {
            return $this->errorResponse('Selected farm does not belong to the selected farmer.', 422);
        }

        $farmer = User::findOrFail($validated['farmer_id']);

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('service_requests', 'public');
        }

        $serviceRequest = ServiceRequest::create([
            'farm_id' => $farm->id,
            'farmer_id' => $farmer->id,
            'type' => $validated['type'],
            'description' => $validated['description'],
            'urgency' => $validated['urgency'],
            'source_channel' => $validated['source_channel'],
            'photo_path' => $photoPath,
            'status' => 'pending',
        ]);

        ActivityLog::log(
            'service_request.admin_created',
            $serviceRequest,
            [
                'admin_id' => $request->user()->id,
                'admin_name' => $request->user()->name,
                'farmer_id' => $farmer->id,
                'farmer_name' => $farmer->name,
                'farm_id' => $farm->id,
                'farm_name' => $farm->farm_name,
                'source_channel' => $serviceRequest->source_channel,
                'type' => $serviceRequest->type,
                'urgency' => $serviceRequest->urgency,
            ]
        );

        \App\Models\AdminNotification::notify(
            'service_request.created_by_admin',
            "Service Request Created: #SR-{$serviceRequest->id}",
            "Customer support initiated a {$serviceRequest->type} service request for {$farm->farm_name} via " . str_replace('_', ' ', $serviceRequest->source_channel) . '.',
            [
                'service_request_id' => $serviceRequest->id,
                'farm_id' => $farm->id,
                'source_channel' => $serviceRequest->source_channel,
                'type' => $serviceRequest->type,
            ],
            $farmer->id
        );

        return $this->createdResponse(
            new ServiceRequestResource($serviceRequest->load(['farm', 'farmer', 'assignedPractitioner', 'fulfilledRecord'])),
            'Service request created successfully on customer\'s behalf'
        );
    }

    /**
     * Assign a service request to a practitioner.
     */
    public function assign(AssignServiceRequestRequest $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $practitioner = User::findOrFail($request->integer('practitioner_id'));

        $previousAssignedTo = $serviceRequest->assigned_to;
        $previousStatus = $serviceRequest->status;

        $serviceRequest->update([
            'assigned_to' => $practitioner->id,
            'status' => 'assigned',
            'assigned_at' => now(),
        ]);

        ActivityLog::log(
            'service_request.assigned',
            $serviceRequest,
            [
                'previous_assigned_to' => $previousAssignedTo,
                'new_assigned_to' => $practitioner->id,
                'practitioner_name' => $practitioner->name,
                'previous_status' => $previousStatus,
                'new_status' => 'assigned',
            ]
        );

        // Notify the requesting farmer
        \App\Models\AdminNotification::notify(
            'service_request.assigned',
            "Practitioner Assigned: #SR-{$serviceRequest->id}",
            "Specialist {$practitioner->name} has been assigned to your service request for {$serviceRequest->farm?->farm_name}.",
            [
                'service_request_id' => $serviceRequest->id,
                'practitioner_id' => $practitioner->id,
                'practitioner_name' => $practitioner->name,
                'practitioner_phone' => $practitioner->phone,
                'farm_id' => $serviceRequest->farm_id,
            ],
            $serviceRequest->farmer_id
        );

        // Notify the assigned practitioner
        \App\Models\AdminNotification::notify(
            'service_request.assigned_practitioner',
            "New Service Request Assigned: #SR-{$serviceRequest->id}",
            "You have been assigned to service request #SR-{$serviceRequest->id} for {$serviceRequest->farm?->farm_name} ({$serviceRequest->farm?->district}). Urgency: " . ucfirst($serviceRequest->urgency),
            [
                'service_request_id' => $serviceRequest->id,
                'farm_id' => $serviceRequest->farm_id,
                'farm_name' => $serviceRequest->farm?->farm_name,
                'district' => $serviceRequest->farm?->district,
                'urgency' => $serviceRequest->urgency,
                'type' => $serviceRequest->type,
                'farmer_name' => $serviceRequest->farmer?->name,
                'farmer_phone' => $serviceRequest->farmer?->phone,
            ],
            $practitioner->id
        );

        return $this->successResponse(
            new ServiceRequestResource($serviceRequest->load(['farm', 'farmer', 'assignedPractitioner', 'fulfilledRecord'])),
            "Service request assigned to {$practitioner->name} successfully"
        );
    }

    /**
     * Get available practitioners with active workload counts and district info.
     * All active practitioners of the required role are returned, with the target
     * district ranked first as a sorting signal rather than a filter.
     */
    public function practitioners(Request $request): JsonResponse
    {
        $rawType = strtolower(trim((string) $request->query('type', '')));
        $role = null;

        if (in_array($rawType, ['vet', 'veterinary', 'veterinary_doctor', 'doctor'])) {
            $role = 'veterinary_doctor';
        } elseif (in_array($rawType, ['consultant', 'advisory', 'specialist'])) {
            $role = 'consultant';
        }

        $query = User::query()->where(function ($q) {
            $q->where('is_active', true)->orWhereNull('is_active');
        });

        if ($role) {
            $query->role($role);
        } else {
            $query->whereHas('roles', function ($q) {
                $q->whereIn('name', ['veterinary_doctor', 'consultant']);
            });
        }

        $query->withCount([
            'assignedServiceRequests as open_requests_count' => function ($q) {
                $q->whereIn('status', ['assigned', 'in_progress']);
            },
        ]);

        $targetDistrict = trim((string) $request->query('district', ''));
        if (! empty($targetDistrict) && ! in_array(strtolower($targetDistrict), ['all', 'all districts', 'null', 'undefined'])) {
            $query->orderByRaw('CASE WHEN LOWER(TRIM(COALESCE(district, \'\'))) = ? THEN 0 ELSE 1 END', [mb_strtolower($targetDistrict)]);
        }

        $query->orderBy('open_requests_count', 'asc')
            ->orderBy('name', 'asc');

        $practitioners = $query->get();

        $data = $practitioners->map(function ($user) {
            $avatarUrl = $user->profile_image_path
                ? \Illuminate\Support\Facades\Storage::disk('public')->url($user->profile_image_path)
                : $user->avatar_url;

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'district' => $user->district,
                'role' => $user->roles->first()?->name,
                'gender' => $user->gender,
                'avatar_url' => $avatarUrl,
                'profile_image_url' => $avatarUrl,
                'open_requests_count' => (int) $user->open_requests_count,
            ];
        });

        return $this->successResponse($data, 'Practitioners retrieved successfully');
    }

    /**
     * Retrieve dashboard metrics for service requests.
     */
    public function metrics(): JsonResponse
    {
        $openCount = ServiceRequest::whereIn('status', ['pending', 'assigned', 'in_progress'])->count();
        $urgentCount = ServiceRequest::whereIn('status', ['pending', 'assigned', 'in_progress'])
            ->where('urgency', 'urgent')
            ->count();
        $completedThisMonth = ServiceRequest::where('status', 'completed')
            ->where('completed_at', '>=', Carbon::now()->startOfMonth())
            ->count();

        // Calculate average turnaround in hours for completed requests (with guard against negative data)
        $completedRequests = ServiceRequest::where('status', 'completed')
            ->whereNotNull('completed_at')
            ->select(['id', 'created_at', 'completed_at'])
            ->get();

        $validTurnaroundHours = [];
        foreach ($completedRequests as $req) {
            $created = Carbon::parse($req->created_at);
            $completed = Carbon::parse($req->completed_at);

            if ($completed->lessThan($created)) {
                \Illuminate\Support\Facades\Log::warning("ServiceRequest #{$req->id} has completed_at ({$completed}) before created_at ({$created}). Excluding from turnaround calculation.");
                continue;
            }

            $validTurnaroundHours[] = $created->diffInHours($completed);
        }

        $avgTurnaroundHours = count($validTurnaroundHours) > 0
            ? round(array_sum($validTurnaroundHours) / count($validTurnaroundHours), 1)
            : 0;

        // Calculate Follow-up completion rate
        $today = Carbon::today();
        $dueVetFollowUps = \App\Models\VetRecord::whereNotNull('next_follow_up')
            ->whereDate('next_follow_up', '<=', $today)
            ->with(['followUpRecords', 'followUpServiceRequest'])
            ->get();

        $dueConsultantFollowUps = \App\Models\ConsultantRecord::whereNotNull('next_follow_up')
            ->whereDate('next_follow_up', '<=', $today)
            ->with(['followUpRecords', 'followUpServiceRequest'])
            ->get();

        $totalScheduled = $dueVetFollowUps->count() + $dueConsultantFollowUps->count();
        $completedCount = 0;

        foreach ($dueVetFollowUps as $rec) {
            if ($rec->followUpRecords->isNotEmpty() || ($rec->followUpServiceRequest && $rec->followUpServiceRequest->status === 'completed')) {
                $completedCount++;
            }
        }

        foreach ($dueConsultantFollowUps as $rec) {
            if ($rec->followUpRecords->isNotEmpty() || ($rec->followUpServiceRequest && $rec->followUpServiceRequest->status === 'completed')) {
                $completedCount++;
            }
        }

        $followUpCompletionRate = $totalScheduled > 0
            ? round(($completedCount / $totalScheduled) * 100, 1)
            : 100.0;

        return $this->successResponse([
            'open_requests' => $openCount,
            'urgent_requests' => $urgentCount,
            'completed_this_month' => $completedThisMonth,
            'avg_turnaround_hours' => $avgTurnaroundHours,
            'follow_up_completion_rate' => $followUpCompletionRate,
            'scheduled_follow_ups_count' => $totalScheduled,
            'completed_follow_ups_count' => $completedCount,
        ], 'Service request metrics retrieved successfully');
    }
}
