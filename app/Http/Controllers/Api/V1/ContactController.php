<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContactRequest;
use App\Models\AdminNotification;
use App\Models\LeadInquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ContactController extends Controller
{
    /**
     * Handle public contact form submission.
     * Persisted into the database (lead_inquiries) and triggers an admin bell alert.
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

        $inquiry = LeadInquiry::create([
            'name' => trim($validated['name']),
            'email' => trim($validated['email']),
            'phone' => isset($validated['phone']) && trim($validated['phone']) !== '' ? trim($validated['phone']) : null,
            'topic' => trim($validated['subject']),
            'message' => trim($validated['message']),
            'source' => 'contact_page',
            'status' => 'new',
        ]);

        // Notify admins/staff via general notification bell
        try {
            $topicBadge = $inquiry->topic ? "[{$inquiry->topic}] " : '';
            $contactInfo = $inquiry->phone ?: $inquiry->email;
            AdminNotification::notify(
                'lead.inquiry',
                'New Contact Message: ' . $inquiry->name,
                "{$contactInfo} - {$topicBadge}" . Str::limit($inquiry->message, 80),
                [
                    'lead_inquiry_id' => $inquiry->id,
                    'phone' => $inquiry->phone,
                    'email' => $inquiry->email,
                    'name' => $inquiry->name,
                    'topic' => $inquiry->topic,
                    'source' => $inquiry->source,
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('AdminNotification dispatch failed for contact inquiry', [
                'error' => $e->getMessage(),
                'id' => $inquiry->id,
            ]);
        }

        Log::info('Contact form submission successfully saved to database', [
            'id' => $inquiry->id,
            'name' => $inquiry->name,
            'email' => $inquiry->email,
            'subject' => $inquiry->topic,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Thank you for contacting FarmLink! Your message has been received. Our team will review your inquiry and get back to you shortly.',
            'data' => [
                'id' => $inquiry->id,
                'name' => $inquiry->name,
                'status' => $inquiry->status,
                'source' => $inquiry->source,
                'created_at' => $inquiry->created_at->toISOString(),
            ],
        ], 200);
    }
}
