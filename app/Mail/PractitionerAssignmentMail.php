<?php

namespace App\Mail;

use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PractitionerAssignmentMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public ServiceRequest $serviceRequest,
        public User $practitioner
    ) {}

    public function envelope(): Envelope
    {
        $urgency = ucfirst($this->serviceRequest->urgency ?? 'Normal');

        return new Envelope(
            subject: "[Assignment] New Service Request #SR-{$this->serviceRequest->id} ({$urgency}) - FarmLink",
        );
    }

    public function content(): Content
    {
        $frontendUrl = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/');
        $portalUrl = "{$frontendUrl}/my/service-requests/{$this->serviceRequest->id}";

        return new Content(
            view: 'emails.practitioners.new_assignment',
            with: [
                'serviceRequest' => $this->serviceRequest,
                'practitioner' => $this->practitioner,
                'portalUrl' => $portalUrl,
            ],
        );
    }
}
