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

        $query = ServiceRequest::with(['farm', 'farmer', 'assignedPractitioner', 'fulfilledRecord'])
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
}
