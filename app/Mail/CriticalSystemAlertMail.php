<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CriticalSystemAlertMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public string $title,
        public string $errorMessage,
        public array $context = []
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[CRITICAL ALERT] {$this->title} - FarmLink",
        );
    }

    public function content(): Content
    {
        $frontendUrl = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/');
        $actionUrl = "{$frontendUrl}/admin/dashboard";

        return new Content(
            view: 'emails.admin.critical_alert',
            with: [
                'title' => $this->title,
                'errorMessage' => $this->errorMessage,
                'context' => $this->context,
                'actionUrl' => $actionUrl,
            ],
        );
    }
}
