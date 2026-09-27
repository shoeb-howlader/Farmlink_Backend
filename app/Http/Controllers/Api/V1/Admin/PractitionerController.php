<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\ConsultantRecord;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\VetRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class PractitionerController extends ApiController
{
    /**
     * Display a practitioner's profile, statistics, and full clinical/advisory visit history.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        Gate::authorize('viewAdmin', \App\Models\Order::class);

        $practitioner = User::with('roles')->findOrFail($id);

        $isVet = $practitioner->hasRole('veterinary_doctor');
        $isConsultant = $practitioner->hasRole('consultant');

        if (! $isVet && ! $isConsultant && ! $practitioner->hasRole('admin')) {
            return $this->errorResponse('The requested user is not an active practitioner.', 404);
        }

        // Stats
        $openAssignments = ServiceRequest::where('assigned_to', $practitioner->id)
            ->whereIn('status', ['assigned', 'in_progress'])
            ->count();

        $completedVisits = ServiceRequest::where('assigned_to', $practitioner->id)
            ->where('status', 'completed')
            ->count();

        $ratedRequests = ServiceRequest::where('assigned_to', $practitioner->id)
            ->whereNotNull('rating');

        $totalRatings = $ratedRequests->count();
        $avgRating = $totalRatings > 0 ? round($ratedRequests->avg('rating'), 1) : 0;

        // Records logged
        $records = collect();

        if ($isVet || $practitioner->hasRole('admin')) {
            $vetRecords = VetRecord::with(['farm.user', 'farm'])
                ->where('vet_id', $practitioner->id)
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

        if ($isConsultant || $practitioner->hasRole('admin')) {
            $consultantRecords = ConsultantRecord::with(['farm.user', 'farm'])
                ->where('consultant_id', $practitioner->id)
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

        // Assigned service requests
        $assignedRequests = ServiceRequest::with(['farm', 'farmer'])
            ->where('assigned_to', $practitioner->id)
            ->latest()
            ->get()
            ->map(function (ServiceRequest $sr) {
                return [
                    'id' => $sr->id,
                    'type' => $sr->type,
                    'urgency' => $sr->urgency,
                    'status' => $sr->status,
                    'description' => $sr->description,
                    'rating' => $sr->rating,
                    'feedback_note' => $sr->feedback_note,
                    'farm' => $sr->farm ? [
                        'id' => $sr->farm->id,
                        'farm_name' => $sr->farm->farm_name,
                        'district' => $sr->farm->district,
                    ] : null,
                    'farmer' => $sr->farmer ? [
                        'id' => $sr->farmer->id,
                        'name' => $sr->farmer->name,
                        'phone' => $sr->farmer->phone,
                    ] : null,
                    'assigned_at' => $sr->assigned_at?->toISOString(),
                    'completed_at' => $sr->completed_at?->toISOString(),
                    'created_at' => $sr->created_at?->toISOString(),
                ];
            });

        // Reviews from completed service requests
        $reviews = ServiceRequest::with(['farm', 'farmer'])
            ->where('assigned_to', $practitioner->id)
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
                        'avatar_url' => $sr->farmer->profile_image_path ? Storage::disk('public')->url($sr->farmer->profile_image_path) : $sr->farmer->avatar_url,
                    ] : null,
                    'completed_at' => $sr->completed_at?->toISOString(),
                ];
            });

        $avatarUrl = $practitioner->profile_image_path
            ? Storage::disk('public')->url($practitioner->profile_image_path)
            : $practitioner->avatar_url;

        $thumbUrl = $practitioner->profile_image_thumbnail_path
            ? Storage::disk('public')->url($practitioner->profile_image_thumbnail_path)
            : $avatarUrl;

        return $this->successResponse([
            'id' => $practitioner->id,
            'name' => $practitioner->name,
            'phone' => $practitioner->phone,
            'email' => $practitioner->email,
            'district' => $practitioner->district,
            'role' => $practitioner->roles->first()?->name ?? 'staff',
            'gender' => $practitioner->gender,
            'is_active' => (bool) ($practitioner->is_active ?? true),
            'avatar_url' => $avatarUrl,
            'avatar_thumbnail_url' => $thumbUrl,
            'profile_image_path' => $practitioner->profile_image_path,
            'created_at' => $practitioner->created_at?->toISOString(),
            'stats' => [
                'average_rating' => $avgRating,
                'total_ratings' => $totalRatings,
                'open_assignments' => $openAssignments,
                'completed_visits' => $completedVisits,
                'total_records_logged' => $records->count(),
            ],
            'records' => $records->sortByDesc('visit_date')->values(),
            'requests' => $assignedRequests,
            'reviews' => $reviews,
        ], 'Practitioner profile retrieved successfully');
    }
}
