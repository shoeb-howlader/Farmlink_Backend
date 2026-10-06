<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\ActivityLog;
use App\Models\AdminNotification;
use App\Models\ConsultantRecord;
use App\Models\Order;
use App\Models\RescheduleRequest;
use App\Models\ServiceRequest;
use App\Models\VetRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RescheduleRequestController extends ApiController
{
    /**
     * List reschedule requests with optional status and search filter.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAdmin', Order::class);

        $query = RescheduleRequest::with(['farm', 'farmer', 'practitioner', 'reviewer']);

        $status = $request->query('status', 'pending');
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($request->filled('search')) {
            $search = trim($request->query('search'));
            $query->where(function ($q) use ($search) {
                $q->where('reason', 'like', "%{$search}%")
                    ->orWhereHas('farm', fn ($fq) => $fq->where('farm_name', 'like', "%{$search}%")->orWhere('district', 'like', "%{$search}%"))
                    ->orWhereHas('farmer', fn ($uq) => $uq->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"))
                    ->orWhereHas('practitioner', fn ($pq) => $pq->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"));
            });
        }

        $pendingCount = RescheduleRequest::where('status', 'pending')->count();
        $perPage = max(1, min((int) $request->query('per_page', 15), 100));
        $paginated = $query->latest('created_at')->paginate($perPage);

        return $this->successResponse([
            'pending_count' => $pendingCount,
            'requests' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ], 'Reschedule requests retrieved successfully');
    }

    /**
     * Lightweight pending count for admin sidebar badge.
     */
    public function count(Request $request): JsonResponse
    {
        Gate::authorize('viewAdmin', Order::class);

        $pendingCount = RescheduleRequest::where('status', 'pending')->count();

        return $this->successResponse([
            'pending_count' => $pendingCount,
        ], 'Pending reschedule requests count retrieved successfully');
    }

    /**
     * Approve a pending follow-up reschedule request.
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        Gate::authorize('viewAdmin', Order::class);

        $req = RescheduleRequest::with(['farm.user', 'practitioner'])->findOrFail($id);

        if ($req->status !== 'pending') {
            return $this->errorResponse("This request has already been {$req->status}.", 422);
        }

        // Fetch underlying record
        $record = $req->record;
        if (! $record) {
            return $this->errorResponse('Underlying clinical visit record could not be found.', 404);
        }

        $oldDate = $record->next_follow_up ? $record->next_follow_up->toDateString() : $req->old_date->toDateString();
        $newDate = $req->new_date->toDateString();

        // 1. Update record follow-up dates and reason
        if (! $record->original_follow_up_date) {
            $record->original_follow_up_date = $record->next_follow_up ?? $req->old_date;
        }
        $record->next_follow_up = $newDate;
        $record->rescheduled_reason = $req->reason;
        $record->rescheduled_at = now();
        $record->rescheduled_by = $req->practitioner_id;
        $record->save();

        // 2. If underlying service request exists, update channel to 'follow_up_rescheduled'
        $recordClass = $req->record_type === 'vet' ? VetRecord::class : ConsultantRecord::class;
        $serviceRequest = null;
        if ($record->follow_up_service_request_id) {
            $serviceRequest = ServiceRequest::find($record->follow_up_service_request_id);
        }
        if (! $serviceRequest) {
            $serviceRequest = ServiceRequest::where('parent_record_type', $recordClass)
                ->where('parent_record_id', $record->id)
                ->first();
        }
        if ($serviceRequest) {
            $serviceRequest->update(['source_channel' => 'follow_up_rescheduled']);
        }

        // 3. Update reschedule request status
        $admin = $request->user();
        $req->update([
            'status' => 'approved',
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        // 4. Notify practitioner
        AdminNotification::notify(
            'follow_up.reschedule_approved',
            "Reschedule Approved: {$req->farm?->farm_name}",
            "Your proposed follow-up reschedule for {$req->farm?->farm_name} from {$oldDate} to {$newDate} was approved.",
            [
                'reschedule_request_id' => $req->id,
                'record_id' => $record->id,
                'record_type' => $req->record_type,
                'old_date' => $oldDate,
                'new_date' => $newDate,
                'reason' => $req->reason,
            ],
            $req->practitioner_id
        );

        // 5. Notify farmer if available
        if ($req->farmer_id) {
            AdminNotification::notify(
                'follow_up.rescheduled',
                "Follow-up Visit Rescheduled",
                "The follow-up visit for {$req->farm?->farm_name} has been rescheduled to {$newDate}.",
                [
                    'record_id' => $record->id,
                    'record_type' => $req->record_type,
                    'new_date' => $newDate,
                    'old_date' => $oldDate,
                ],
                $req->farmer_id
            );
        }

        // 6. Audit Log
        ActivityLog::log(
            'follow_up.reschedule_approved',
            $req,
            [
                'reschedule_request_id' => $req->id,
                'record_id' => $record->id,
                'record_type' => $req->record_type,
                'old_date' => $oldDate,
                'new_date' => $newDate,
                'reason' => $req->reason,
                'practitioner_id' => $req->practitioner_id,
                'approved_by' => $admin->id,
            ],
            $admin
        );

        return $this->successResponse([
            'reschedule_request' => $req->fresh(['farm', 'farmer', 'practitioner', 'reviewer']),
            'new_date' => $newDate,
            'record_id' => $record->id,
        ], 'Follow-up reschedule request approved successfully.');
    }

    /**
     * Reject a pending follow-up reschedule request.
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        Gate::authorize('viewAdmin', Order::class);

        $req = RescheduleRequest::with(['farm.user', 'practitioner'])->findOrFail($id);

        if ($req->status !== 'pending') {
            return $this->errorResponse("This request has already been {$req->status}.", 422);
        }

        $validated = $request->validate([
            'admin_note' => 'nullable|string|max:1000',
        ]);

        $admin = $request->user();
        $adminNote = ! empty($validated['admin_note']) ? trim($validated['admin_note']) : null;

        $req->update([
            'status' => 'rejected',
            'admin_note' => $adminNote,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        // Notify practitioner
        $noteSnippet = $adminNote ? " Note: {$adminNote}" : '';
        AdminNotification::notify(
            'follow_up.reschedule_rejected',
            "Reschedule Rejected: {$req->farm?->farm_name}",
            "Your proposed follow-up reschedule for {$req->farm?->farm_name} to {$req->new_date->toDateString()} was rejected. The follow-up remains scheduled for {$req->old_date->toDateString()}.{$noteSnippet}",
            [
                'reschedule_request_id' => $req->id,
                'record_id' => $req->record_id,
                'record_type' => $req->record_type,
                'old_date' => $req->old_date->toDateString(),
                'proposed_date' => $req->new_date->toDateString(),
                'admin_note' => $adminNote,
            ],
            $req->practitioner_id
        );

        // Audit Log
        ActivityLog::log(
            'follow_up.reschedule_rejected',
            $req,
            [
                'reschedule_request_id' => $req->id,
                'record_id' => $req->record_id,
                'record_type' => $req->record_type,
                'old_date' => $req->old_date->toDateString(),
                'proposed_date' => $req->new_date->toDateString(),
                'admin_note' => $adminNote,
                'rejected_by' => $admin->id,
            ],
            $admin
        );

        return $this->successResponse([
            'reschedule_request' => $req->fresh(['farm', 'farmer', 'practitioner', 'reviewer']),
        ], 'Follow-up reschedule request rejected.');
    }
}
