<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContactRequest;
use App\Mail\ContactSubmissionMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    /**
     * Handle public contact form submission.
     * Protected by rate limiting (throttle:5,1) and honeypot spam protection.
     */
    public function send(ContactRequest $request): JsonResponse
    {
        // Honeypot spam defense check: if bot filled hidden field, return silent success
        if ($request->filled('honeypot') || $request->filled('website')) {
            Log::warning('Contact form submission rejected due to filled honeypot field', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Thank you for contacting FarmLink! Your message has been received.',
            ], 200);
        }

        $validated = $request->validated();

        $recipient = config('mail.reply_to.address')
            ?: config('mail.from.address')
            ?: env('CONTACT_MAIL_RECIPIENT', 'support@farmlink.com');

        try {
            Mail::to($recipient)->queue(new ContactSubmissionMail(
                name: $validated['name'],
                email: $validated['email'],
                inquirySubject: $validated['subject'],
                messageText: $validated['message'],
                ipAddress: $request->ip(),
                submittedAt: now()->toDayDateTimeString(),
            ));

            Log::info('Contact form submission successfully received and queued', [
                'email' => $validated['email'],
                'subject' => $validated['subject'],
                'ip' => $request->ip(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to queue contact form email', [
                'error' => $e->getMessage(),
                'email' => $validated['email'],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Thank you for contacting FarmLink! Your message has been sent successfully. Our support team will review your inquiry and get back to you shortly.',
        ], 200);
    }
}
