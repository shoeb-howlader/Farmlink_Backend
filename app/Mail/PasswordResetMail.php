<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $token,
        public ?string $otp = null
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reset Your FarmLink Account Password',
        );
    }

    public function content(): Content
    {
        $frontendUrl = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/');
        $resetUrl = "{$frontendUrl}/reset-password?token={$this->token}&email=".urlencode($this->user->email);

        return new Content(
            view: 'emails.auth.password_reset',
            with: [
                'user' => $this->user,
                'resetUrl' => $resetUrl,
                'otp' => $this->otp,
            ],
        );
    }
}
