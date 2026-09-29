<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreConsultantRecordRequest;
use App\Http\Resources\V1\ConsultantRecordResource;
use App\Models\ActivityLog;
use App\Models\ConsultantRecord;
use App\Models\Farm;
use App\Models\ServiceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ConsultantRecordController extends ApiController
{
    /**
     * Display a listing of consultant records for the specified farm.
     */
    public function index(Request $request, Farm $farm): JsonResponse
    {
        Gate::authorize('viewAny', [ConsultantRecord::class, $farm]);

        $query = $farm->consultantRecords()
            ->with(['consultant', 'farm', 'prescription.items.product', 'testResults', 'photos', 'parentRecord', 'followUpRecords'])
            ->latest('visit_date');

        if ($request->user()->hasRole('consultant') && ! $request->user()->hasRole('admin')) {
            $query->where('consultant_id', $request->user()->id);
        }

        $records = $query->get();

        return $this->successResponse(
            ConsultantRecordResource::collection($records),
            'Consultant records retrieved successfully'
        );
    }

    /**
     * Store a newly created consultant record for the specified farm.
     */
    public function store(StoreConsultantRecordRequest $request, Farm $farm): JsonResponse
    {
        Gate::authorize('create', [ConsultantRecord::class, $farm]);

        $record = DB::transaction(function () use ($request, $farm) {
            $data = $request->validated();
            $serviceRequestId = $data['service_request_id'] ?? null;
            $recommendations = $data['recommendations'] ?? $data['prescription_items'] ?? null;
            $testResults = $data['test_results'] ?? null;
            unset($data['service_request_id'], $data['prescription_items'], $data['recommendations'], $data['test_results']);

            $record = $farm->consultantRecords()->create(array_merge(
                $data,
                ['consultant_id' => $request->user()->id]
            ));

            if (! empty($recommendations)) {
                $prescription = $record->prescription()->create();
                foreach ($recommendations as $item) {
                    $prescription->items()->create([
                        'type' => $item['type'] ?? 'other',
                        'medicine_name' => $item['item_name'] ?? $item['medicine_name'] ?? '',
                        'dosage' => $item['dosage'] ?? null,
                        'frequency' => $item['frequency'] ?? null,
                        'duration' => $item['duration'] ?? null,
                        'instructions' => $item['instructions'] ?? $item['reasoning'] ?? null,
                        'reasoning' => $item['reasoning'] ?? $item['instructions'] ?? null,
                        'product_id' => $item['product_id'] ?? null,
                    ]);
                }
            }

            if (! empty($testResults)) {
                foreach ($testResults as $result) {
                    $record->testResults()->create([
                        'parameter' => $result['parameter'],
                        'value' => $result['value'],
                        'unit' => $result['unit'] ?? null,
                        'reference_range' => $result['reference_range'] ?? null,
                        'flag' => $result['flag'] ?? null,
                    ]);
                }
            }

            if ($request->filled('photos')) {
                foreach ($request->input('photos') as $idx => $photoData) {
                    if (! empty($photoData['photo_path'])) {
                        $record->photos()->create([
                            'photo_path' => $photoData['photo_path'],
                            'caption' => $photoData['caption'] ?? null,
                            'sort_order' => $photoData['sort_order'] ?? $idx,
                        ]);
                    }
                }
            }

            if ($serviceRequestId) {
                $serviceRequest = ServiceRequest::find($serviceRequestId);
                if ($serviceRequest && $serviceRequest->farm_id === $farm->id) {
                    if ($serviceRequest->parent_record_id && ! $record->parent_record_id && $serviceRequest->parent_record_type === ConsultantRecord::class) {
                        $record->update(['parent_record_id' => $serviceRequest->parent_record_id]);
                    }

                    $serviceRequest->update([
                        'status' => 'completed',
                        'completed_at' => now(),
                        'fulfilled_record_type' => ConsultantRecord::class,
                        'fulfilled_record_id' => $record->id,
                    ]);

                    ActivityLog::log('service_request.completed', $serviceRequest, [
                        'record_id' => $record->id,
                        'record_type' => 'consultant',
                        'has_prescription' => ! empty($prescriptionItems),
                    ]);

                    \App\Models\AdminNotification::notify(
                        'service_request.completed',
                        "Service Request Completed: #SR-{$serviceRequest->id}",
                        "Consultant {$request->user()->name} has completed the service request for {$farm->farm_name} and filed an advisory report.",
                        [
                            'service_request_id' => $serviceRequest->id,
                            'practitioner_id' => $request->user()->id,
                            'practitioner_name' => $request->user()->name,
                            'record_id' => $record->id,
                            'record_type' => 'consultant',
                            'farm_id' => $farm->id,
                        ],
                        $serviceRequest->farmer_id
                    );
                }
            }

            return $record;
        });

        return $this->createdResponse(
            new ConsultantRecordResource($record->load(['consultant', 'farm', 'prescription.items.product', 'testResults', 'photos', 'parentRecord', 'followUpRecords'])),
            'Consultant record created successfully'
        );
    }
}
