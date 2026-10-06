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

class OrderStatusChangedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public string $oldStatus,
        public string $newStatus,
        public ?string $pdfBinary = null
    ) {}

    public function envelope(): Envelope
    {
        $statusLabels = [
            'confirmed' => 'Confirmed',
            'dispatched' => 'Dispatched',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
            'returned' => 'Returned',
        ];
        $statusLabel = $statusLabels[$this->newStatus] ?? ucfirst($this->newStatus);

        return new Envelope(
            subject: "Order #{$this->order->id} Status: {$statusLabel} - FarmLink",
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
                'oldStatus' => $this->oldStatus,
                'newStatus' => $this->newStatus,
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
                \Illuminate\Support\Facades\Log::warning("[OrderStatusChangedMail] Could not generate invoice PDF: " . $e->getMessage());
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
