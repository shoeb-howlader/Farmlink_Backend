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

        $query = $farm->vetRecords()
            ->with(['vet', 'farm', 'prescription.items.product', 'testResults', 'photos', 'parentRecord', 'followUpRecords'])
            ->latest('visit_date');

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
            $prescriptionItems = $data['prescription_items'] ?? null;
            $testResults = $data['test_results'] ?? null;
            unset($data['service_request_id'], $data['prescription_items'], $data['test_results']);

            // Auto-summarize medicine_given if prescription items provided and medicine_given is empty
            if (! empty($prescriptionItems) && empty($data['medicine_given'])) {
                $medNames = array_map(function ($item) {
                    $name = $item['medicine_name'] ?? '';
                    if (! empty($item['dosage'])) {
                        $name .= ' (' . $item['dosage'] . ')';
                    }

                    return $name;
                }, $prescriptionItems);
                $data['medicine_given'] = implode(', ', array_filter($medNames));
            }

            if (empty($data['treatment'])) {
                $data['treatment'] = ! empty($prescriptionItems)
                    ? 'Prescribed medical treatment protocol as detailed in clinical prescription.'
                    : 'Clinical assessment and diagnostic examination conducted.';
            }

            $record = $farm->vetRecords()->create(array_merge(
                $data,
                ['vet_id' => $request->user()->id]
            ));

            if (! empty($prescriptionItems)) {
                $prescription = $record->prescription()->create();
                foreach ($prescriptionItems as $item) {
                    $prescription->items()->create([
                        'medicine_name' => $item['medicine_name'],
                        'dosage' => $item['dosage'] ?? null,
                        'frequency' => $item['frequency'] ?? null,
                        'duration' => $item['duration'] ?? null,
                        'instructions' => $item['instructions'] ?? null,
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
                    // If service request originated from a parent record, link it
                    if ($serviceRequest->parent_record_id && ! $record->parent_record_id && $serviceRequest->parent_record_type === VetRecord::class) {
                        $record->update(['parent_record_id' => $serviceRequest->parent_record_id]);
                    }

                    $serviceRequest->update([
                        'status' => 'completed',
                        'completed_at' => now(),
                        'fulfilled_record_type' => VetRecord::class,
                        'fulfilled_record_id' => $record->id,
                    ]);

                    ActivityLog::log('service_request.completed', $serviceRequest, [
                        'record_id' => $record->id,
                        'record_type' => 'vet',
                        'has_prescription' => ! empty($prescriptionItems),
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

                    // Supplementary email channel with visit report PDF attachment for farmer
                    $farmerUser = $serviceRequest->farmer ?? $farm->user;
                    if ($farmerUser && $farmerUser->email) {
                        try {
                            $pdfBinary = app(\App\Services\PdfDocumentService::class)->generateVisitReportPdf($record, true, $serviceRequest);
                            \Illuminate\Support\Facades\Mail::to($farmerUser->email)
                                ->queue(new \App\Mail\VisitReportMail($serviceRequest, $record, $farmerUser, $request->user(), true, $pdfBinary));
                        } catch (\Throwable $e) {
                            \Illuminate\Support\Facades\Log::warning("[VISIT REPORT MAIL ERROR] Could not queue email for SR #{$serviceRequest->id}: " . $e->getMessage());
                        }
                    }
                }
            }

            return $record;
        });

        return $this->createdResponse(
            new VetRecordResource($record->load(['vet', 'farm', 'prescription.items.product', 'testResults', 'photos', 'parentRecord', 'followUpRecords'])),
            'Vet record created successfully'
        );
    }
}
