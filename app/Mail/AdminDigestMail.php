<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminDigestMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $stats
     * @param  string  $frequency  'daily' or 'weekly'
     */
    public function __construct(
        public User $admin,
        public array $stats,
        public string $frequency = 'daily'
    ) {}

    public function envelope(): Envelope
    {
        $freqName = ucfirst($this->frequency);
        $dateStr = now()->toFormattedDateString();

        return new Envelope(
            subject: "[FarmLink] {$freqName} Operations Digest - {$dateStr}",
        );
    }

    public function content(): Content
    {
        $frontendUrl = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/');
        $dashboardUrl = "{$frontendUrl}/admin/dashboard";

        return new Content(
            view: 'emails.admin.daily_digest',
            with: array_merge([
                'admin' => $this->admin,
                'frequency' => $this->frequency,
                'dashboardUrl' => $dashboardUrl,
                'reportDate' => now()->toFormattedDateString(),
            ], $this->stats),
        );
    }
}
