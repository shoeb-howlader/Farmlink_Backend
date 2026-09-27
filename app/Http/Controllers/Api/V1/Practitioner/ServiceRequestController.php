<?php

namespace App\Http\Controllers\Api\V1\Practitioner;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\V1\ServiceRequestResource;
use App\Models\ServiceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceRequestController extends ApiController
{
    /**
     * Display service requests assigned to the authenticated practitioner.
     */
    public function myRequests(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = ServiceRequest::with(['farm', 'farmer', 'fulfilledRecord'])
            ->where('assigned_to', $user->id)
            ->urgentFirst();

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $requests = $query->paginate($request->integer('per_page', 15));

        return $this->successResponse(
            ServiceRequestResource::collection($requests)->response()->getData(true),
            'Assigned service requests retrieved successfully'
        );
    }
}
