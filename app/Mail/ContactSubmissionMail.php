<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactSubmissionMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public string $name,
        public string $email,
        public string $inquirySubject,
        public string $messageText,
        public ?string $ipAddress = null,
        public ?string $submittedAt = null
    ) {
        $this->subject = "[Contact Inquiry] {$inquirySubject} - FarmLink";
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[Contact Inquiry] {$this->inquirySubject} - FarmLink",
            replyTo: [
                new Address($this->email, $this->name),
            ],
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.contact_submission',
            with: [
                'name' => $this->name,
                'email' => $this->email,
                'subject' => $this->inquirySubject,
                'messageText' => $this->messageText,
                'ipAddress' => $this->ipAddress,
                'submittedAt' => $this->submittedAt ?? now()->toDayDateTimeString(),
            ],
        );
    }
}
