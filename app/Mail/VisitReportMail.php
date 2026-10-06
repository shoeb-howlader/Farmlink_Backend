<?php

namespace App\Mail;

use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VisitReportMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public ServiceRequest $serviceRequest,
        public mixed $record,
        public User $farmer,
        public User $practitioner,
        public bool $isVet = true,
        public ?string $pdfBinary = null
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Visit Report & Prescription (#SR-{$this->serviceRequest->id}) - FarmLink",
        );
    }

    public function content(): Content
    {
        $frontendUrl = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/');
        $viewUrl = "{$frontendUrl}/dashboard";

        return new Content(
            view: 'emails.reports.visit_report',
            with: [
                'serviceRequest' => $this->serviceRequest,
                'record' => $this->record,
                'farmer' => $this->farmer,
                'practitioner' => $this->practitioner,
                'isVet' => $this->isVet,
                'hasAttachment' => ! empty($this->pdfBinary),
                'viewUrl' => $viewUrl,
            ],
        );
    }

    public function attachments(): array
    {
        if (empty($this->pdfBinary)) {
            try {
                $this->pdfBinary = app(\App\Services\PdfDocumentService::class)->generateVisitReportPdf(
                    $this->serviceRequest,
                    $this->record,
                    $this->farmer,
                    $this->practitioner,
                    $this->isVet
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("[VisitReportMail] Could not generate visit report PDF: " . $e->getMessage());
                return [];
            }
        }

        $prefix = $this->isVet ? 'Vet-Visit-Report' : 'Consultant-Visit-Report';
        $filename = "{$prefix}-#{$this->record->id}.pdf";

        return [
            Attachment::fromData(fn () => $this->pdfBinary, $filename)
                ->withMime('application/pdf'),
        ];
    }
}
