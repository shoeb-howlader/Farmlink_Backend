<?php

namespace App\Services;

use App\Models\Farm;
use App\Models\Order;
use App\Models\ServiceRequest;
use Barryvdh\DomPDF\Facade\Pdf;

class PdfDocumentService
{
    /**
     * Generate the raw PDF binary string for an Order Invoice.
     */
    public function generateOrderInvoicePdf(Order $order): string
    {
        $order->loadMissing(['items.product', 'items.variant', 'user', 'farm']);

        $pdf = Pdf::loadView('invoices.order', [
            'order' => $order,
        ]);

        $pdf->setPaper('a4', 'portrait');

        return $pdf->output();
    }

    /**
     * Generate the raw PDF binary string for a Visit Report & Prescription.
     */
    public function generateVisitReportPdf(
        $record,
        bool $isVet,
        ?ServiceRequest $serviceRequest = null
    ): string {
        $record->loadMissing(['farm.user', 'prescription.items.product', 'testResults', 'photos']);

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

        return $pdf->output();
    }

    /**
     * Generate the raw PDF binary string for a Farm Profile Dossier.
     */
    public function generateFarmDossierPdf(Farm $farm, array $options = []): string
    {
        $farm->loadMissing([
            'user',
            'division',
            'districtModel',
            'upazilaModel',
            'unionModel',
            'pourashava',
            'images',
        ]);

        $telemetryQuery = $farm->telemetries()->with('user')->orderBy('recorded_at', 'desc');
        $recentTelemetries = (clone $telemetryQuery)->take(8)->get();
        $telemetryCount = (clone $telemetryQuery)->count();
        $avgDo = $farm->telemetries()->whereNotNull('dissolved_oxygen')->avg('dissolved_oxygen');
        $avgPh = $farm->telemetries()->whereNotNull('ph')->avg('ph');
        $avgSalinity = $farm->telemetries()->whereNotNull('salinity')->avg('salinity');
        $avgTemp = $farm->telemetries()->whereNotNull('temperature')->avg('temperature');

        $activeCycle = method_exists($farm, 'currentCycle')
            ? $farm->currentCycle
            : (method_exists($farm, 'cycles') ? $farm->cycles()->whereNull('end_date')->latest('start_date')->first() : null);

        $vetVisitsCount = method_exists($farm, 'vetRecords') ? $farm->vetRecords()->count() : 0;
        $consultantVisitsCount = method_exists($farm, 'consultantRecords') ? $farm->consultantRecords()->count() : 0;
        $ordersCount = method_exists($farm, 'orders') ? $farm->orders()->count() : 0;

        $pdf = Pdf::loadView('reports.farm_dossier', [
            'farm' => $farm,
            'recentTelemetries' => $recentTelemetries,
            'telemetryCount' => $telemetryCount,
            'avgDo' => $avgDo,
            'avgPh' => $avgPh,
            'avgSalinity' => $avgSalinity,
            'avgTemp' => $avgTemp,
            'activeCycle' => $activeCycle,
            'vetVisitsCount' => $vetVisitsCount,
            'consultantVisitsCount' => $consultantVisitsCount,
            'ordersCount' => $ordersCount,
            'options' => $options,
        ]);

        $pdf->setPaper('a4', 'portrait');

        return $pdf->output();
    }
}

