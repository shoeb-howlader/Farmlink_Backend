<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class InvoiceController extends ApiController
{
    /**
     * Generate and stream/download a PDF invoice for a farmer's order.
     */
    public function farmerInvoice(Request $request, Order $order): Response
    {
        Gate::authorize('view', $order);

        return $this->buildPdfResponse($request, $order);
    }

    /**
     * Generate and stream/download a PDF invoice for any order (Admin / Staff).
     */
    public function adminInvoice(Request $request, Order $order): Response
    {
        Gate::authorize('viewAdmin', Order::class);

        return $this->buildPdfResponse($request, $order);
    }

    /**
     * Build the PDF binary response from the invoice Blade template.
     */
    protected function buildPdfResponse(Request $request, Order $order): Response
    {
        $order->loadMissing(['items.product', 'user', 'farm']);

        $filename = "Invoice-{$order->invoice_number}.pdf";
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        $cacheKey = "order_invoice_pdf_{$order->id}_" . ($order->updated_at ? $order->updated_at->timestamp : '0');
        $pdfOutput = \Illuminate\Support\Facades\Cache::remember($cacheKey, 86400, function () use ($order) {
            $pdf = Pdf::loadView('invoices.order', [
                'order' => $order,
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
