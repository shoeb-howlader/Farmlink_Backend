<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AdminNotification;
use App\Models\LeadInquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LeadInquiryController extends ApiController
{
    /**
     * Submit a guest lead inquiry (public endpoint for landing/catalog visitors).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'phone' => ['required', 'string', 'min:11', 'max:25'],
            'email' => ['nullable', 'email', 'max:100'],
            'topic' => ['nullable', 'string', 'max:100'],
            'message' => ['required', 'string', 'min:3', 'max:1500'],
            'source' => ['nullable', 'string', 'max:50'],
        ]);

        $inquiry = LeadInquiry::create([
            'name' => trim($validated['name']),
            'phone' => trim($validated['phone']),
            'email' => isset($validated['email']) && trim($validated['email']) !== '' ? trim($validated['email']) : null,
            'topic' => isset($validated['topic']) && trim($validated['topic']) !== '' ? trim($validated['topic']) : null,
            'message' => trim($validated['message']),
            'source' => isset($validated['source']) && trim($validated['source']) !== '' ? trim($validated['source']) : 'homepage_widget',
            'status' => 'new',
        ]);

        // Notify admins/staff via general notification bell (visually distinct lead alert)
        try {
            $topicBadge = $inquiry->topic ? "[{$inquiry->topic}] " : '';
            AdminNotification::notify(
                'lead.inquiry',
                'Website Lead: ' . $inquiry->name,
                "{$inquiry->phone} - {$topicBadge}" . Str::limit($inquiry->message, 80),
                [
                    'lead_inquiry_id' => $inquiry->id,
                    'phone' => $inquiry->phone,
                    'name' => $inquiry->name,
                    'topic' => $inquiry->topic,
                    'source' => $inquiry->source,
                ]
            );
        } catch (\Throwable $e) {
            // Log or ignore notification failure so user submission succeeds
        }

        return $this->createdResponse([
            'id' => $inquiry->id,
            'name' => $inquiry->name,
            'phone' => $inquiry->phone,
            'topic' => $inquiry->topic,
            'source' => $inquiry->source,
            'status' => $inquiry->status,
            'created_at' => $inquiry->created_at->toISOString(),
        ], 'Thank you! Your message has been received. Our aquaculture advisory team will reach out to you shortly.');
    }

    /**
     * List inquiries for admin review with filtering, searching, and stats counts.
     */
    public function index(Request $request): JsonResponse
    {
        $query = LeadInquiry::query()->latest();

        if ($request->filled('status') && $request->query('status') !== 'all') {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('search')) {
            $search = trim($request->query('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('topic', 'like', "%{$search}%")
                  ->orWhere('message', 'like', "%{$search}%");
            });
        }

        $inquiries = $query->paginate($request->integer('per_page', 20));

        $counts = [
            'all' => LeadInquiry::count(),
            'new' => LeadInquiry::where('status', 'new')->count(),
            'contacted' => LeadInquiry::where('status', 'contacted')->count(),
            'converted' => LeadInquiry::where('status', 'converted')->count(),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Lead inquiries retrieved successfully.',
            'data' => $inquiries,
            'counts' => $counts,
        ]);
    }

    /**
     * Update lead inquiry status (e.g. mark contacted, mark converted).
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:new,contacted,converted,closed,spam'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $inquiry = LeadInquiry::findOrFail($id);
        $inquiry->status = $validated['status'];
        if (array_key_exists('notes', $validated)) {
            $inquiry->notes = $validated['notes'];
        }
        $inquiry->save();

        return $this->successResponse($inquiry, 'Inquiry status updated successfully.');
    }

    /**
     * Delete an inquiry (for spam or duplicate records).
     */
    public function destroy(int $id): JsonResponse
    {
        $inquiry = LeadInquiry::findOrFail($id);
        $inquiry->delete();

        return $this->successResponse(null, 'Inquiry removed successfully.');
    }
}
