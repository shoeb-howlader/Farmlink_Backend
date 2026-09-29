<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ConsultantRecord;
use App\Models\Prescription;
use App\Models\ServiceRequest;
use App\Models\VetRecord;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class VisitReportController extends ApiController
{
    /**
     * Download or stream visit report for a service request.
     */
    public function downloadForServiceRequest(Request $request, ServiceRequest $serviceRequest): Response
    {
        $user = $request->user();

        // Load farm & farmer
        $serviceRequest->loadMissing(['farm.user', 'assignedPractitioner']);
        $farm = $serviceRequest->farm;
        $farmer = $farm?->user;

        // Authorization check
        $isAssigned = $serviceRequest->assigned_to === $user->id;
        $isFarmer = ($serviceRequest->farmer_id === $user->id) || ($farmer && $farmer->id === $user->id);
        $isAdminOrStaff = $user->hasRole(['admin', 'data_entry_operator']);

        abort_if(! ($isAssigned || $isFarmer || $isAdminOrStaff), 403, 'Unauthorized to view this visit report.');

        // Find associated clinical/advisory record
        $isVet = $serviceRequest->type === 'vet';
        $record = null;

        if ($serviceRequest->fulfilled_record_type && $serviceRequest->fulfilled_record_id) {
            $record = $serviceRequest->fulfilled_record_type::with([
                'farm.user',
                $isVet ? 'vet' : 'consultant',
                'prescription.items.product',
                'testResults',
                'photos',
            ])->find($serviceRequest->fulfilled_record_id);
        }

        if (! $record) {
            if ($isVet) {
                $record = VetRecord::where('farm_id', $serviceRequest->farm_id)
                    ->where(function ($q) use ($serviceRequest) {
                        $q->where('id', $serviceRequest->fulfilled_record_id)
                            ->orWhere('visit_date', $serviceRequest->created_at?->format('Y-m-d'));
                    })
                    ->with(['farm.user', 'vet', 'prescription.items.product', 'testResults', 'photos'])
                    ->latest()
                    ->first();
            } else {
                $record = ConsultantRecord::where('farm_id', $serviceRequest->farm_id)
                    ->where(function ($q) use ($serviceRequest) {
                        $q->where('id', $serviceRequest->fulfilled_record_id)
                            ->orWhere('visit_date', $serviceRequest->created_at?->format('Y-m-d'));
                    })
                    ->with(['farm.user', 'consultant', 'prescription.items.product', 'testResults', 'photos'])
                    ->latest()
                    ->first();
            }
        }

        abort_if(! $record, 404, 'No visit report has been filed yet for this service request.');

        return $this->generatePdfResponse($request, $record, $isVet, $serviceRequest);
    }

    /**
     * Download or stream visit report for a vet record.
     */
    public function downloadForVetRecord(Request $request, VetRecord $vetRecord): Response
    {
        $vetRecord->loadMissing([
            'farm.user',
            'vet',
            'prescription.items.product',
            'testResults',
            'photos',
        ]);

        $user = $request->user();
        $farm = $vetRecord->farm;
        $farmer = $farm?->user;

        $isPractitioner = $vetRecord->vet_id === $user->id;
        $isFarmer = ($vetRecord->farm?->user_id === $user->id) || ($farmer && $farmer->id === $user->id);
        $isAdminOrStaff = $user->hasRole(['admin', 'data_entry_operator']);

        abort_if(! ($isPractitioner || $isFarmer || $isAdminOrStaff), 403, 'Unauthorized to view this visit report.');

        $serviceRequest = ServiceRequest::where('fulfilled_record_type', VetRecord::class)
            ->where('fulfilled_record_id', $vetRecord->id)
            ->first();

        return $this->generatePdfResponse($request, $vetRecord, true, $serviceRequest);
    }

    /**
     * Download or stream visit report for a consultant record.
     */
    public function downloadForConsultantRecord(Request $request, ConsultantRecord $consultantRecord): Response
    {
        $consultantRecord->loadMissing([
            'farm.user',
            'consultant',
            'prescription.items.product',
            'testResults',
            'photos',
        ]);

        $user = $request->user();
        $farm = $consultantRecord->farm;
        $farmer = $farm?->user;

        $isPractitioner = $consultantRecord->consultant_id === $user->id;
        $isFarmer = ($consultantRecord->farm?->user_id === $user->id) || ($farmer && $farmer->id === $user->id);
        $isAdminOrStaff = $user->hasRole(['admin', 'data_entry_operator']);

        abort_if(! ($isPractitioner || $isFarmer || $isAdminOrStaff), 403, 'Unauthorized to view this visit report.');

        $serviceRequest = ServiceRequest::where('fulfilled_record_type', ConsultantRecord::class)
            ->where('fulfilled_record_id', $consultantRecord->id)
            ->first();

        return $this->generatePdfResponse($request, $consultantRecord, false, $serviceRequest);
    }

    /**
     * Helper to render PDF response.
     */
    protected function generatePdfResponse(
        Request $request,
        $record,
        bool $isVet,
        ?ServiceRequest $serviceRequest = null
    ): Response {
        $farm = $record->farm;
        $farmer = $farm?->user;
        $practitioner = $isVet ? $record->vet : $record->consultant;
        $prescription = $record->prescription;
        $prescriptionItems = $prescription ? $prescription->items : collect();
        $testResults = $record->testResults ?? collect();
        $photos = $record->photos ?? collect();

        $pdf = Pdf::loadView('reports.visit_report', [
            'record' => $record,
            'farm' => $farm,
            'farmer' => $farmer,
            'practitioner' => $practitioner,
            'isVet' => $isVet,
            'prescription' => $prescription,
            'prescriptionItems' => $prescriptionItems,
            'testResults' => $testResults,
            'photos' => $photos,
            'serviceRequest' => $serviceRequest,
        ]);

        $pdf->setPaper('a4', 'portrait');

        $prefix = $isVet ? 'Vet-Visit-Report' : 'Consultant-Visit-Report';
        $filename = "{$prefix}-#{$record->id}.pdf";

        if ($request->boolean('download')) {
            return $pdf->download($filename);
        }

        return $pdf->stream($filename);
    }
}
