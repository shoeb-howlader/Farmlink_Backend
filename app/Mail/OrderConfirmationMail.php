<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderConfirmationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  string|null  $pdfBinary  Base64 encoded or raw PDF string
     */
    public function __construct(
        public Order $order,
        public ?string $pdfBinary = null
    ) {
        if ($this->pdfBinary === null) {
            try {
                $this->pdfBinary = app(\App\Services\PdfDocumentService::class)->generateOrderInvoicePdf($order);
            } catch (\Throwable $e) {
                // Ignore in case PDF driver unavailable
            }
        }
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Order Confirmation #{$this->order->id} - FarmLink",
        );
    }

    public function content(): Content
    {
        $frontendUrl = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/');
        $orderUrl = "{$frontendUrl}/orders/{$this->order->id}";

        return new Content(
            view: 'emails.orders.order_status',
            with: [
                'order' => $this->order,
                'hasAttachment' => ! empty($this->pdfBinary),
                'orderUrl' => $orderUrl,
            ],
        );
    }

    public function attachments(): array
    {
        if (empty($this->pdfBinary)) {
            try {
                $this->pdfBinary = app(\App\Services\PdfDocumentService::class)->generateOrderInvoicePdf($this->order);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("[OrderConfirmationMail] Could not generate invoice PDF: " . $e->getMessage());
                return [];
            }
        }

        $filename = "Invoice-{$this->order->invoice_number}.pdf";

        return [
            Attachment::fromData(fn () => $this->pdfBinary, $filename)
                ->withMime('application/pdf'),
        ];
    }
}
