<?php

namespace App\Http\Controllers\Api\V1\Practitioner;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\ActivityLog;
use App\Models\AdminNotification;
use App\Models\ConsultantRecord;
use App\Models\ServiceRequest;
use App\Models\VetRecord;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PractitionerPortalController extends ApiController
{
    /**
     * Retrieve dashboard metrics and newly assigned requests for the authenticated practitioner.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasAnyRole(['veterinary_doctor', 'consultant', 'admin'])) {
            return $this->errorResponse('Access restricted to veterinary doctors and consultants.', 403);
        }

        $openAssignedCount = ServiceRequest::where('assigned_to', $user->id)
            ->whereIn('status', ['assigned', 'in_progress'])
            ->count();

        $fulfilledThisMonth = ServiceRequest::where('assigned_to', $user->id)
            ->where('status', 'completed')
            ->where('completed_at', '>=', Carbon::now()->startOfMonth())
            ->count();

        $totalCompleted = ServiceRequest::where('assigned_to', $user->id)
            ->where('status', 'completed')
            ->count();

        $ratedRequests = ServiceRequest::where('assigned_to', $user->id)
            ->whereNotNull('rating');

        $totalRatings = $ratedRequests->count();
        $avgRating = $totalRatings > 0 ? round((float) $ratedRequests->avg('rating'), 1) : 0.0;

        // Pending action requests (newly assigned, urgently sorted)
        $pendingActionRequests = ServiceRequest::with(['farm', 'farmer', 'parentRecord'])
            ->where('assigned_to', $user->id)
            ->where('status', 'assigned')
            ->urgentFirst()
            ->limit(5)
            ->get()
            ->map(function (ServiceRequest $sr) {
                $slaThresholdHours = $sr->getSlaThresholdHours();
                $isOverdue = $sr->assigned_at
                    ? $sr->assigned_at->copy()->addHours($slaThresholdHours)->isPast()
                    : ($sr->created_at ? $sr->created_at->copy()->addHours($slaThresholdHours)->isPast() : false);

                $parentData = null;
                if ($sr->parentRecord) {
                    $parentData = [
                        'id' => $sr->parentRecord->id,
                        'visit_date' => $sr->parentRecord->visit_date?->toDateString(),
                        'clinical_findings' => $sr->parentRecord->clinical_findings ?? $sr->parentRecord->findings ?? null,
                    ];
                }

                return [
                    'id' => $sr->id,
                    'type' => $sr->type,
                    'urgency' => $sr->urgency,
                    'status' => $sr->status,
                    'source_channel' => $sr->source_channel ?? 'self_service',
                    'parent_record_id' => $sr->parent_record_id,
                    'parent_record_type' => $sr->parent_record_type,
                    'parent_record' => $parentData,
                    'description' => $sr->description,
                    'sla_threshold_hours' => $slaThresholdHours,
                    'is_overdue' => $isOverdue,
                    'farm' => $sr->farm ? [
                        'id' => $sr->farm->id,
                        'farm_name' => $sr->farm->farm_name,
                        'district' => $sr->farm->district,
                        'upazila' => $sr->farm->upazila,
                        'address' => $sr->farm->address,
                        'pond_count' => $sr->farm->pond_count,
                        'total_water_area_decimals' => $sr->farm->total_water_area_decimals,
                        'average_water_depth_ft' => $sr->farm->average_water_depth_ft,
                        'water_salinity_ppt' => $sr->farm->water_salinity_ppt,
                        'main_culture_type' => $sr->farm->main_culture_type,
                        'farming_system' => $sr->farm->farming_system,
                        'has_aerators' => $sr->farm->has_aerators,
                        'created_at' => $sr->farm->created_at?->toDateString(),
                    ] : null,
                    'farmer' => $sr->farmer ? [
                        'id' => $sr->farmer->id,
                        'name' => $sr->farmer->name,
                        'phone' => $sr->farmer->phone,
                    ] : null,
                    'assigned_at' => $sr->assigned_at?->toISOString(),
                    'created_at' => $sr->created_at?->toISOString(),
                ];
            });

        // Upcoming follow-ups for this practitioner (sorted soonest first)
        $isVet = $user->hasRole('veterinary_doctor');
        $isConsultant = $user->hasRole('consultant');
        $isAdmin = $user->hasRole('admin');

        $pendingReschedules = \App\Models\RescheduleRequest::where('practitioner_id', $user->id)
            ->where('status', 'pending')
            ->get()
            ->keyBy(fn ($r) => "{$r->record_type}-{$r->record_id}");

        $upcomingFollowUps = collect();

        if ($isVet || $isAdmin) {
            $vetFollowUps = VetRecord::with(['farm.user', 'farm'])
                ->where('vet_id', $user->id)
                ->whereNotNull('next_follow_up')
                ->whereDate('next_follow_up', '>=', Carbon::today())
                ->orderBy('next_follow_up', 'asc')
                ->limit(5)
                ->get()
                ->map(function (VetRecord $rec) use ($pendingReschedules) {
                    $pending = $pendingReschedules->get("vet-{$rec->id}");

                    return [
                        'id' => $rec->id,
                        'type' => 'vet',
                        'visit_date' => $rec->visit_date?->toDateString(),
                        'next_follow_up' => $rec->next_follow_up?->toDateString(),
                        'original_follow_up_date' => $rec->original_follow_up_date?->toDateString(),
                        'rescheduled_reason' => $rec->rescheduled_reason,
                        'rescheduled_at' => $rec->rescheduled_at?->toISOString(),
                        'rescheduled_by' => $rec->rescheduled_by,
                        'pending_reschedule' => $pending ? [
                            'id' => $pending->id,
                            'new_date' => $pending->new_date->toDateString(),
                            'reason' => $pending->reason,
                            'status' => $pending->status,
                        ] : null,
                        'summary' => $rec->treatment ?: ($rec->findings ?: 'Veterinary checkup'),
                        'findings' => $rec->findings,
                        'treatment' => $rec->treatment,
                        'medicine_given' => $rec->medicine_given,
                        'farm' => $rec->farm ? [
                            'id' => $rec->farm->id,
                            'farm_name' => $rec->farm->farm_name,
                            'district' => $rec->farm->district,
                            'upazila' => $rec->farm->upazila,
                            'address' => $rec->farm->address,
                            'pond_count' => $rec->farm->pond_count,
                            'total_water_area_decimals' => $rec->farm->total_water_area_decimals,
                            'average_water_depth_ft' => $rec->farm->average_water_depth_ft,
                            'water_salinity_ppt' => $rec->farm->water_salinity_ppt,
                            'main_culture_type' => $rec->farm->main_culture_type,
                            'farming_system' => $rec->farm->farming_system,
                            'has_aerators' => $rec->farm->has_aerators,
                        ] : null,
                        'farmer' => $rec->farm?->user ? [
                            'id' => $rec->farm->user->id,
                            'name' => $rec->farm->user->name,
                            'phone' => $rec->farm->user->phone,
                        ] : null,
                    ];
                });
            $upcomingFollowUps = $upcomingFollowUps->concat($vetFollowUps);
        }

        if ($isConsultant || $isAdmin) {
            $consultantFollowUps = ConsultantRecord::with(['farm.user', 'farm'])
                ->where('consultant_id', $user->id)
                ->whereNotNull('next_follow_up')
                ->whereDate('next_follow_up', '>=', Carbon::today())
                ->orderBy('next_follow_up', 'asc')
                ->limit(5)
                ->get()
                ->map(function (ConsultantRecord $rec) use ($pendingReschedules) {
                    $pending = $pendingReschedules->get("consultant-{$rec->id}");

                    return [
                        'id' => $rec->id,
                        'type' => 'consultant',
                        'visit_date' => $rec->visit_date?->toDateString(),
                        'next_follow_up' => $rec->next_follow_up?->toDateString(),
                        'original_follow_up_date' => $rec->original_follow_up_date?->toDateString(),
                        'rescheduled_reason' => $rec->rescheduled_reason,
                        'rescheduled_at' => $rec->rescheduled_at?->toISOString(),
                        'rescheduled_by' => $rec->rescheduled_by,
                        'pending_reschedule' => $pending ? [
                            'id' => $pending->id,
                            'new_date' => $pending->new_date->toDateString(),
                            'reason' => $pending->reason,
                            'status' => $pending->status,
                        ] : null,
                        'summary' => $rec->recommendation ?: 'Aquaculture advisory visit',
                        'recommendation' => $rec->recommendation,
                        'farm' => $rec->farm ? [
                            'id' => $rec->farm->id,
                            'farm_name' => $rec->farm->farm_name,
                            'district' => $rec->farm->district,
                            'upazila' => $rec->farm->upazila,
                            'address' => $rec->farm->address,
                            'pond_count' => $rec->farm->pond_count,
                            'total_water_area_decimals' => $rec->farm->total_water_area_decimals,
                            'average_water_depth_ft' => $rec->farm->average_water_depth_ft,
                            'water_salinity_ppt' => $rec->farm->water_salinity_ppt,
                            'main_culture_type' => $rec->farm->main_culture_type,
                            'farming_system' => $rec->farm->farming_system,
                            'has_aerators' => $rec->farm->has_aerators,
                        ] : null,
                        'farmer' => $rec->farm?->user ? [
                            'id' => $rec->farm->user->id,
                            'name' => $rec->farm->user->name,
                            'phone' => $rec->farm->user->phone,
                        ] : null,
                    ];
                });
            $upcomingFollowUps = $upcomingFollowUps->concat($consultantFollowUps);
        }

        $sortedFollowUps = $upcomingFollowUps->sortBy('next_follow_up')->values()->take(5);

        return $this->successResponse([
            'practitioner' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->roles->first()?->name,
                'district' => $user->district,
            ],
            'metrics' => [
                'open_assigned_count' => $openAssignedCount,
                'fulfilled_this_month_count' => $fulfilledThisMonth,
                'total_completed_count' => $totalCompleted,
                'average_rating' => $avgRating,
                'total_ratings_count' => $totalRatings,
            ],
            'pending_action_requests' => $pendingActionRequests,
            'upcoming_follow_ups' => $sortedFollowUps,
        ], 'Practitioner dashboard metrics retrieved successfully');
    }

    /**
     * Retrieve the practitioner's logged visit history and client reviews.
     */
    public function history(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasAnyRole(['veterinary_doctor', 'consultant', 'admin'])) {
            return $this->errorResponse('Access restricted to veterinary doctors and consultants.', 403);
        }

        $isVet = $user->hasRole('veterinary_doctor');
        $isConsultant = $user->hasRole('consultant');
        $isAdmin = $user->hasRole('admin');

        $records = collect();

        if ($isVet || $isAdmin) {
            $vetRecords = VetRecord::with(['farm.user', 'farm', 'prescription.items.product', 'parentRecord'])
                ->where('vet_id', $user->id)
                ->latest('visit_date')
                ->get()
                ->map(function (VetRecord $rec) {
                    return [
                        'id' => $rec->id,
                        'type' => 'vet',
                        'visit_date' => $rec->visit_date?->toDateString(),
                        'findings' => $rec->findings,
                        'treatment' => $rec->treatment,
                        'medicine_given' => $rec->medicine_given,
                        'recommendation' => null,
                        'next_follow_up' => $rec->next_follow_up?->toDateString(),
                        'original_follow_up_date' => $rec->original_follow_up_date?->toDateString(),
                        'rescheduled_reason' => $rec->rescheduled_reason,
                        'rescheduled_at' => $rec->rescheduled_at?->toISOString(),
                        'rescheduled_by' => $rec->rescheduled_by,
                        'parent_record_id' => $rec->parent_record_id,
                        'prescription' => $rec->prescription ? [
                            'id' => $rec->prescription->id,
                            'pdf_url' => url("/api/v1/prescriptions/{$rec->prescription->id}/pdf"),
                            'items' => $rec->prescription->items->map(fn($item) => [
                                'id' => $item->id,
                                'medicine_name' => $item->medicine_name,
                                'dosage' => $item->dosage,
                                'frequency' => $item->frequency,
                                'duration' => $item->duration,
                                'instructions' => $item->instructions,
                                'product_id' => $item->product_id,
                                'product' => $item->product ? [
                                    'id' => $item->product->id,
                                    'name' => $item->product->name,
                                    'price' => $item->product->price,
                                    'stock' => $item->product->stock,
                                ] : null,
                            ]),
                        ] : null,
                        'farm' => $rec->farm ? [
                            'id' => $rec->farm->id,
                            'farm_name' => $rec->farm->farm_name,
                            'district' => $rec->farm->district,
                        ] : null,
                        'farmer' => $rec->farm?->user ? [
                            'id' => $rec->farm->user->id,
                            'name' => $rec->farm->user->name,
                            'phone' => $rec->farm->user->phone,
                        ] : null,
                        'created_at' => $rec->created_at?->toISOString(),
                    ];
                });
            $records = $records->concat($vetRecords);
        }

        if ($isConsultant || $isAdmin) {
            $consultantRecords = ConsultantRecord::with(['farm.user', 'farm', 'prescription.items.product', 'parentRecord'])
                ->where('consultant_id', $user->id)
                ->latest('visit_date')
                ->get()
                ->map(function (ConsultantRecord $rec) {
                    return [
                        'id' => $rec->id,
                        'type' => 'consultant',
                        'visit_date' => $rec->visit_date?->toDateString(),
                        'findings' => null,
                        'treatment' => null,
                        'medicine_given' => null,
                        'recommendation' => $rec->recommendation,
                        'next_follow_up' => $rec->next_follow_up?->toDateString(),
                        'original_follow_up_date' => $rec->original_follow_up_date?->toDateString(),
                        'rescheduled_reason' => $rec->rescheduled_reason,
                        'rescheduled_at' => $rec->rescheduled_at?->toISOString(),
                        'rescheduled_by' => $rec->rescheduled_by,
                        'parent_record_id' => $rec->parent_record_id,
                        'prescription' => $rec->prescription ? [
                            'id' => $rec->prescription->id,
                            'pdf_url' => url("/api/v1/prescriptions/{$rec->prescription->id}/pdf"),
                            'items' => $rec->prescription->items->map(fn($item) => [
                                'id' => $item->id,
                                'medicine_name' => $item->medicine_name,
                                'dosage' => $item->dosage,
                                'frequency' => $item->frequency,
                                'duration' => $item->duration,
                                'instructions' => $item->instructions,
                                'product_id' => $item->product_id,
                                'product' => $item->product ? [
                                    'id' => $item->product->id,
                                    'name' => $item->product->name,
                                    'price' => $item->product->price,
                                    'stock' => $item->product->stock,
                                ] : null,
                            ]),
                        ] : null,
                        'farm' => $rec->farm ? [
                            'id' => $rec->farm->id,
                            'farm_name' => $rec->farm->farm_name,
                            'district' => $rec->farm->district,
                        ] : null,
                        'farmer' => $rec->farm?->user ? [
                            'id' => $rec->farm->user->id,
                            'name' => $rec->farm->user->name,
                            'phone' => $rec->farm->user->phone,
                        ] : null,
                        'created_at' => $rec->created_at?->toISOString(),
                    ];
                });
            $records = $records->concat($consultantRecords);
        }

        // Reviews received on completed requests
        $reviews = ServiceRequest::with(['farm', 'farmer'])
            ->where('assigned_to', $user->id)
            ->whereNotNull('rating')
            ->orderByDesc('completed_at')
            ->get()
            ->map(function (ServiceRequest $sr) {
                return [
                    'id' => $sr->id,
                    'rating' => $sr->rating,
                    'feedback_note' => $sr->feedback_note,
                    'type' => $sr->type,
                    'farm' => $sr->farm ? [
                        'id' => $sr->farm->id,
                        'farm_name' => $sr->farm->farm_name,
                        'district' => $sr->farm->district,
                    ] : null,
                    'farmer' => $sr->farmer ? [
                        'id' => $sr->farmer->id,
                        'name' => $sr->farmer->name,
                        'phone' => $sr->farmer->phone,
                        'gender' => $sr->farmer->gender,
                        'avatar_url' => $sr->farmer->profile_image_path
                            ? Storage::disk('public')->url($sr->farmer->profile_image_path)
                            : $sr->farmer->avatar_url,
                    ] : null,
                    'completed_at' => $sr->completed_at?->toISOString(),
                ];
            });

        return $this->successResponse([
            'records' => $records->sortByDesc('visit_date')->values(),
            'reviews' => $reviews,
        ], 'Practitioner service history retrieved successfully');
    }

    /**
     * Reschedule an upcoming follow-up for a visit record (Delegates to PractitionerFollowUpController).
     */
    public function rescheduleFollowUp(Request $request, string $type, int $id): JsonResponse
    {
        return app(PractitionerFollowUpController::class)->reschedule($request, $type, $id);
    }
}
