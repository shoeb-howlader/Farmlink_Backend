<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreVetRecordRequest;
use App\Http\Resources\V1\VetRecordResource;
use App\Models\ActivityLog;
use App\Models\Farm;
use App\Models\ServiceRequest;
use App\Models\VetRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class VetRecordController extends ApiController
{
    /**
     * Display a listing of vet records for the specified farm.
     */
    public function index(Request $request, Farm $farm): JsonResponse
    {
        Gate::authorize('viewAny', [VetRecord::class, $farm]);

        $query = $farm->vetRecords()->with(['vet', 'farm'])->latest('visit_date');

        if ($request->user()->hasRole('veterinary_doctor') && ! $request->user()->hasRole('admin')) {
            $query->where('vet_id', $request->user()->id);
        }

        $records = $query->get();

        return $this->successResponse(
            VetRecordResource::collection($records),
            'Vet records retrieved successfully'
        );
    }

    /**
     * Store a newly created vet record for the specified farm.
     */
    public function store(StoreVetRecordRequest $request, Farm $farm): JsonResponse
    {
        Gate::authorize('create', [VetRecord::class, $farm]);

        $record = DB::transaction(function () use ($request, $farm) {
            $data = $request->validated();
            $serviceRequestId = $data['service_request_id'] ?? null;
            unset($data['service_request_id']);

            $record = $farm->vetRecords()->create(array_merge(
                $data,
                ['vet_id' => $request->user()->id]
            ));

            if ($serviceRequestId) {
                $serviceRequest = ServiceRequest::find($serviceRequestId);
                if ($serviceRequest && $serviceRequest->farm_id === $farm->id) {
                    $serviceRequest->update([
                        'status' => 'completed',
                        'completed_at' => now(),
                        'fulfilled_record_type' => VetRecord::class,
                        'fulfilled_record_id' => $record->id,
                    ]);

                    ActivityLog::log('service_request.completed', $serviceRequest, [
                        'record_id' => $record->id,
                        'record_type' => 'vet',
                    ]);

                    \App\Models\AdminNotification::notify(
                        'service_request.completed',
                        "Service Request Completed: #SR-{$serviceRequest->id}",
                        "Dr. {$request->user()->name} has completed the service request for {$farm->farm_name} and filed a medical report.",
                        [
                            'service_request_id' => $serviceRequest->id,
                            'practitioner_id' => $request->user()->id,
                            'practitioner_name' => $request->user()->name,
                            'record_id' => $record->id,
                            'record_type' => 'vet',
                            'farm_id' => $farm->id,
                        ],
                        $serviceRequest->farmer_id
                    );
                }
            }

            return $record;
        });

        return $this->createdResponse(
            new VetRecordResource($record->load(['vet', 'farm'])),
            'Vet record created successfully'
        );
    }
}
