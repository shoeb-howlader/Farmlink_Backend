<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Prescription;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PrescriptionController extends ApiController
{
    /**
     * Download or stream prescription PDF.
     */
    public function downloadPdf(Request $request, Prescription $prescription): Response
    {
        $prescription->loadMissing([
            'items.product',
            'vetRecord.farm.user',
            'vetRecord.vet',
            'vetRecord.testResults',
            'consultantRecord.farm.user',
            'consultantRecord.consultant',
            'consultantRecord.testResults',
        ]);

        $record = $prescription->vetRecord ?? $prescription->consultantRecord;
        abort_if(! $record, 404, 'Prescription record not found.');

        $farm = $record->farm;
        $farmer = $farm?->user;
        $user = $request->user();

        // Authorization check
        $isPractitioner = ($prescription->vetRecord && $prescription->vetRecord->vet_id === $user->id)
            || ($prescription->consultantRecord && $prescription->consultantRecord->consultant_id === $user->id);
        $isFarmer = $farmer && $farmer->id === $user->id;
        $isAdminOrStaff = $user->hasRole(['admin', 'data_entry_operator']);

        abort_if(! ($isPractitioner || $isFarmer || $isAdminOrStaff), 403, 'Unauthorized to view this prescription.');

        $isVet = (bool) $prescription->vet_record_id;
        $practitioner = $isVet ? $prescription->vetRecord->vet : $prescription->consultantRecord->consultant;
        $testResults = $record->testResults ?? collect();

        $filename = "Visit-Report-{$prescription->id}.pdf";
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        $cacheKey = "prescription_pdf_{$prescription->id}_" . ($prescription->updated_at ? $prescription->updated_at->timestamp : '0');
        $pdfOutput = \Illuminate\Support\Facades\Cache::remember($cacheKey, 86400, function () use ($prescription, $record, $farm, $farmer, $practitioner, $isVet, $testResults) {
            $pdf = Pdf::loadView('reports.visit_report', [
                'prescription' => $prescription,
                'prescriptionItems' => $prescription->items,
                'testResults' => $testResults,
                'record' => $record,
                'farm' => $farm,
                'farmer' => $farmer,
                'practitioner' => $practitioner,
                'isVet' => $isVet,
                'serviceRequest' => null,
            ]);

            $pdf->setPaper('a4', 'portrait');

            return $pdf->output();
        });

        return response($pdfOutput, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "{$disposition}; filename=\"{$filename}\"",
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
