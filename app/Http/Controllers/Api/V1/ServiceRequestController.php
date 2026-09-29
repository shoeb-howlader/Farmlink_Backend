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

        $query = ServiceRequest::with(['farm', 'farmer', 'assignedPractitioner', 'fulfilledRecord', 'parentRecord'])
            ->urgentFirst();

        if (! $user->hasRole('admin')) {
            $query->where('farmer_id', $user->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
        }

        if ($request->filled('urgency')) {
            $query->where('urgency', $request->query('urgency'));
        }

        $serviceRequests = $query->paginate($request->integer('per_page', 15));

        return $this->successResponse(
            ServiceRequestResource::collection($serviceRequests)->response()->getData(true),
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
