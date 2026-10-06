<?php

namespace App\Http\Controllers\Api\V1\Practitioner;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\V1\ServiceRequestResource;
use App\Models\ServiceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceRequestController extends ApiController
{
    public const RECENT_COMPLETED_HOURS = ServiceRequest::RECENT_COMPLETED_HOURS;

    /**
     * Display service requests assigned to the authenticated practitioner.
     */
    public function myRequests(Request $request): JsonResponse
    {
        $user = $request->user();

        $baseQuery = ServiceRequest::where('assigned_to', $user->id);

        $counts = [
            'pending' => (clone $baseQuery)->whereIn('status', ['assigned', 'in_progress', 'pending'])->count(),
            'urgent_pending' => (clone $baseQuery)->whereIn('status', ['assigned', 'in_progress', 'pending'])->where('urgency', 'urgent')->count(),
            'completed' => (clone $baseQuery)->recentlyCompleted(self::RECENT_COMPLETED_HOURS)->count(),
            'all' => (clone $baseQuery)->assignedQueueVisible(self::RECENT_COMPLETED_HOURS)->count(),
        ];

        $query = ServiceRequest::with(['farm', 'farmer', 'fulfilledRecord.photos', 'parentRecord'])
            ->where('assigned_to', $user->id)
            ->urgentFirst();

        if ($request->filled('status')) {
            $status = $request->query('status');
            if ($status === 'pending' || $status === 'assigned') {
                $query->whereIn('status', ['assigned', 'in_progress', 'pending']);
            } elseif ($status === 'completed') {
                $query->recentlyCompleted(self::RECENT_COMPLETED_HOURS);
            } elseif ($status === 'all') {
                $query->assignedQueueVisible(self::RECENT_COMPLETED_HOURS);
            } else {
                $query->where('status', $status);
            }
        } else {
            $query->assignedQueueVisible(self::RECENT_COMPLETED_HOURS);
        }

        if ($request->filled('urgency')) {
            $query->where('urgency', $request->query('urgency'));
        }

        if ($request->filled('search')) {
            $search = trim($request->query('search'));
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('farm', function ($fq) use ($search) {
                        $fq->where('farm_name', 'like', "%{$search}%")
                            ->orWhere('district', 'like', "%{$search}%");
                    })
                    ->orWhereHas('farmer', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        $requests = $query->paginate($request->integer('per_page', 15));
        $responseData = ServiceRequestResource::collection($requests)->response()->getData(true);
        $responseData['counts'] = $counts;

        return $this->successResponse(
            $responseData,
            'Assigned service requests retrieved successfully'
        );
    }
}
