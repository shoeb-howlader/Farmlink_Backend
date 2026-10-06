<?php

namespace App\Services;

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
}
