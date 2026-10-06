<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\ServiceRequestFeedbackRequest;
use App\Http\Requests\Api\V1\StoreServiceRequestRequest;
use App\Http\Resources\V1\ServiceRequestResource;
use App\Models\ActivityLog;
use App\Models\Farm;
use App\Models\ServiceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceRequestController extends ApiController
{
    /**
     * Display a listing of service requests for the authenticated farmer.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = ServiceRequest::with([
            'farm',
            'farmer',
            'assignedPractitioner',
            'fulfilledRecord' => function (\Illuminate\Database\Eloquent\Relations\MorphTo $morphTo) {
                $morphTo->morphWith([
                    \App\Models\VetRecord::class => ['prescription.items.product', 'vet', 'farm', 'testResults', 'photos'],
                    \App\Models\ConsultantRecord::class => ['prescription.items.product', 'consultant', 'farm', 'testResults', 'photos'],
                ]);
            },
            'parentRecord' => function (\Illuminate\Database\Eloquent\Relations\MorphTo $morphTo) {
                $morphTo->morphWith([
                    \App\Models\VetRecord::class => ['prescription.items.product', 'vet', 'farm', 'testResults', 'photos'],
                    \App\Models\ConsultantRecord::class => ['prescription.items.product', 'consultant', 'farm', 'testResults', 'photos'],
                ]);
            },
        ])->urgentFirst();

        if (! $user->hasRole('admin')) {
            $query->where('farmer_id', $user->id);
        }

        if ($request->filled('status') && $request->query('status') !== 'all') {
            $status = $request->query('status');
            if ($status === 'pending' || $status === 'open') {
                $query->whereIn('status', ['pending', 'assigned', 'in_progress']);
            } elseif ($status === 'pending_only') {
                $query->where('status', 'pending');
            } else {
                $query->where('status', $status);
            }
        }

        if ($request->filled('type') && $request->query('type') !== 'all') {
            $query->where('type', $request->query('type'));
        }

        if ($request->filled('urgency') && $request->query('urgency') !== 'all') {
            $query->where('urgency', $request->query('urgency'));
        }

        if ($request->filled('search')) {
            $search = trim($request->query('search'));
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhereHas('farm', function ($fq) use ($search) {
                        $fq->where('farm_name', 'like', "%{$search}%")
                            ->orWhere('district', 'like', "%{$search}%");
                    })
                    ->orWhereHas('assignedPractitioner', function ($pq) use ($search) {
                        $pq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $baseCountQuery = ServiceRequest::query();
        if (! $user->hasRole('admin')) {
            $baseCountQuery->where('farmer_id', $user->id);
        }

        $countsRaw = (clone $baseCountQuery)->selectRaw("
            count(*) as all_count,
            count(case when status in ('pending', 'assigned', 'in_progress') then 1 end) as pending_count,
            count(case when status = 'completed' then 1 end) as completed_count,
            count(case when status in ('pending', 'assigned', 'in_progress') and urgency = 'urgent' then 1 end) as urgent_count
        ")->first();

        $counts = [
            'all' => (int) ($countsRaw->all_count ?? 0),
            'pending' => (int) ($countsRaw->pending_count ?? 0),
            'completed' => (int) ($countsRaw->completed_count ?? 0),
            'urgent' => (int) ($countsRaw->urgent_count ?? 0),
        ];

        $serviceRequests = $query->paginate($request->integer('per_page', 15));
        $data = ServiceRequestResource::collection($serviceRequests)->response()->getData(true);
        $data['counts'] = $counts;

        return $this->successResponse(
            $data,
            'Service requests retrieved successfully'
        );
    }

    /**
     * Display service request details for farmer or practitioner.
     */
    public function show(Request $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $user = $request->user();
        if ($serviceRequest->farmer_id !== $user->id && ! $user->hasRole('admin') && ! $user->hasRole('data_entry_operator') && $serviceRequest->assigned_to !== $user->id) {
            return $this->errorResponse('Unauthorized to view this service request', 403);
        }

        $serviceRequest->loadMissing([
            'farm',
            'farmer',
            'assignedPractitioner',
            'fulfilledRecord',
            'parentRecord',
        ]);

        return $this->successResponse(
            new ServiceRequestResource($serviceRequest),
            'Service request details retrieved successfully'
        );
    }

    /**
     * Store a newly created service request for the specified farm.
     */
    public function store(StoreServiceRequestRequest $request, Farm $farm): JsonResponse
    {
        $user = $request->user();

        if ($user->is_blacklisted) {
            return $this->errorResponse('Your account is currently restricted from requesting field consultations. Please contact customer support.', 403);
        }

        if ($user->consultations_blocked) {
            return $this->errorResponse('Consultations and pond doctor visit requests are currently restricted for your account. Please contact FarmLink customer support at 01711223344 for assistance.', 403);
        }

        if ($farm->user_id !== $user->id && ! $user->hasRole('admin')) {
            return $this->errorResponse('Unauthorized to request services for this farm', 403);
        }

        $data = $request->validated();
        $photoPath = null;

        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('service_requests', 'public');
        }

        $serviceRequest = ServiceRequest::create([
            'farm_id' => $farm->id,
            'farmer_id' => $user->id,
            'type' => $data['type'],
            'description' => $data['description'],
            'urgency' => $data['urgency'],
            'photo_path' => $photoPath,
            'status' => 'pending',
        ]);

        ActivityLog::log(
            'service_request.created',
            $serviceRequest,
            [
                'type' => $serviceRequest->type,
                'urgency' => $serviceRequest->urgency,
                'farm_id' => $farm->id,
            ]
        );

        return $this->createdResponse(
            new ServiceRequestResource($serviceRequest->load(['farm', 'farmer', 'assignedPractitioner', 'fulfilledRecord'])),
            'Service request submitted successfully'
        );
    }

    /**
     * Submit rating and feedback for a completed service request.
     */
    public function feedback(ServiceRequestFeedbackRequest $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $user = $request->user();

        if ($serviceRequest->farmer_id !== $user->id && ! $user->hasRole('admin')) {
            return $this->errorResponse('Unauthorized to submit feedback for this request', 403);
        }

        if ($serviceRequest->status !== 'completed') {
            return $this->errorResponse('Feedback can only be provided for completed service requests', 422);
        }

        if ($serviceRequest->rating !== null) {
            return $this->errorResponse('This service request has already been rated and cannot be re-rated.', 422);
        }

        $serviceRequest->update([
            'rating' => $request->integer('rating'),
            'feedback_note' => $request->input('feedback_note'),
        ]);

        ActivityLog::log(
            'service_request.feedback_added',
            $serviceRequest,
            [
                'rating' => $serviceRequest->rating,
                'feedback_note' => $serviceRequest->feedback_note,
            ]
        );

        return $this->successResponse(
            new ServiceRequestResource($serviceRequest->load(['farm', 'farmer', 'assignedPractitioner', 'fulfilledRecord'])),
            'Feedback submitted successfully'
        );
    }

    /**
     * Retrieve previous visits for the farm associated with this service request.
     */
    public function previousVisits(Request $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $user = $request->user();
        if ($serviceRequest->farmer_id !== $user->id && ! $user->hasRole('admin') && ! $user->hasRole('data_entry_operator') && $serviceRequest->assigned_to !== $user->id) {
            return $this->errorResponse('Unauthorized to view previous visits for this request', 403);
        }

        $farm = $serviceRequest->farm;
        if (! $farm) {
            return $this->successResponse([], 'No farm associated with this service request');
        }

        $vetRecords = $farm->vetRecords()
            ->with(['vet', 'photos'])
            ->latest('visit_date')
            ->limit(5)
            ->get()
            ->map(function ($record) use ($serviceRequest) {
                $isOriginating = ($serviceRequest->parent_record_id == $record->id && $serviceRequest->type === 'vet');
                return [
                    'id' => $record->id,
                    'type' => 'vet',
                    'type_label' => 'Veterinary Visit',
                    'visit_date' => $record->visit_date ? $record->visit_date->toDateString() : null,
                    'practitioner_name' => $record->vet?->name ?? 'Attending Vet',
                    'practitioner_role' => 'Veterinary Doctor',
                    'findings_summary' => \Illuminate\Support\Str::limit($record->findings, 140),
                    'full_findings' => $record->findings,
                    'treatment' => $record->treatment,
                    'next_follow_up' => $record->next_follow_up ? $record->next_follow_up->toDateString() : null,
                    'has_follow_up' => !empty($record->next_follow_up),
                    'report_url' => "/vet-records/{$record->id}/visit-report",
                    'photos_count' => $record->photos->count(),
                    'is_originating' => $isOriginating,
                ];
            });

        $consultantRecords = $farm->consultantRecords()
            ->with(['consultant', 'photos'])
            ->latest('visit_date')
            ->limit(5)
            ->get()
            ->map(function ($record) use ($serviceRequest) {
                $isOriginating = ($serviceRequest->parent_record_id == $record->id && $serviceRequest->type === 'consultant');
                return [
                    'id' => $record->id,
                    'type' => 'consultant',
                    'type_label' => 'Consultant Advisory',
                    'visit_date' => $record->visit_date ? $record->visit_date->toDateString() : null,
                    'practitioner_name' => $record->consultant?->name ?? 'Attending Consultant',
                    'practitioner_role' => 'Aquaculture Consultant',
                    'findings_summary' => \Illuminate\Support\Str::limit($record->recommendation, 140),
                    'full_findings' => $record->recommendation,
                    'treatment' => null,
                    'next_follow_up' => $record->next_follow_up ? $record->next_follow_up->toDateString() : null,
                    'has_follow_up' => !empty($record->next_follow_up),
                    'report_url' => "/consultant-records/{$record->id}/visit-report",
                    'photos_count' => $record->photos->count(),
                    'is_originating' => $isOriginating,
                ];
            });

        $combined = $vetRecords->concat($consultantRecords)
            ->sortByDesc('visit_date')
            ->values()
            ->take(5);

        return $this->successResponse($combined, 'Previous visits retrieved successfully');
    }
}
