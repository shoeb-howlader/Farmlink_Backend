<?php

namespace App\Http\Controllers\Api\V1\Practitioner;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\ActivityLog;
use App\Models\AdminNotification;
use App\Models\ConsultantRecord;
use App\Models\RescheduleRequest;
use App\Models\VetRecord;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PractitionerFollowUpController extends ApiController
{
    /**
     * Retrieve all follow-ups for the authenticated practitioner,
     * grouped by date range: Overdue, Today, This Week, Next 2 Weeks, Later.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasAnyRole(['veterinary_doctor', 'consultant', 'admin'])) {
            return $this->errorResponse('Access restricted to veterinary doctors and consultants.', 403);
        }

        $isVet = $user->hasRole('veterinary_doctor');
        $isConsultant = $user->hasRole('consultant');
        $isAdmin = $user->hasRole('admin');

        $search = $request->filled('search') ? trim((string) $request->query('search')) : null;
        $district = $request->filled('district') && $request->query('district') !== 'all'
            ? trim((string) $request->query('district'))
            : null;

        // Pending reschedule requests by this practitioner
        $pendingReschedules = RescheduleRequest::where('practitioner_id', $user->id)
            ->where('status', 'pending')
            ->get()
            ->keyBy(fn ($r) => "{$r->record_type}-{$r->record_id}");

        $items = collect();
        $allDistricts = collect();

        if ($isVet || $isAdmin) {
            $vetQuery = VetRecord::with(['farm.user', 'farm'])
                ->where('vet_id', $user->id)
                ->whereNotNull('next_follow_up');

            $vetRecords = $vetQuery->get()->map(function (VetRecord $rec) use ($pendingReschedules) {
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
                    'summary' => $rec->treatment ?: ($rec->findings ?: 'Veterinary checkup'),
                    'findings' => $rec->findings,
                    'treatment' => $rec->treatment,
                    'medicine_given' => $rec->medicine_given,
                    'recommendation' => null,
                    'pending_reschedule' => $pending ? [
                        'id' => $pending->id,
                        'new_date' => $pending->new_date->toDateString(),
                        'reason' => $pending->reason,
                        'status' => $pending->status,
                        'created_at' => $pending->created_at->toISOString(),
                    ] : null,
                    'farm' => $rec->farm ? [
                        'id' => $rec->farm->id,
                        'farm_name' => $rec->farm->farm_name,
                        'district' => $rec->farm->district,
                        'upazila' => $rec->farm->upazila,
                        'address' => $rec->farm->address,
                        'pond_count' => $rec->farm->pond_count,
                        'total_water_area_decimals' => $rec->farm->total_water_area_decimals,
                    ] : null,
                    'farmer' => $rec->farm?->user ? [
                        'id' => $rec->farm->user->id,
                        'name' => $rec->farm->user->name,
                        'phone' => $rec->farm->user->phone,
                    ] : null,
                ];
            });

            $items = $items->concat($vetRecords);
        }

        if ($isConsultant || $isAdmin) {
            $consultantQuery = ConsultantRecord::with(['farm.user', 'farm'])
                ->where('consultant_id', $user->id)
                ->whereNotNull('next_follow_up');

            $consultantRecords = $consultantQuery->get()->map(function (ConsultantRecord $rec) use ($pendingReschedules) {
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
                    'summary' => $rec->recommendation ?: 'Aquaculture advisory visit',
                    'findings' => null,
                    'treatment' => null,
                    'medicine_given' => null,
                    'recommendation' => $rec->recommendation,
                    'pending_reschedule' => $pending ? [
                        'id' => $pending->id,
                        'new_date' => $pending->new_date->toDateString(),
                        'reason' => $pending->reason,
                        'status' => $pending->status,
                        'created_at' => $pending->created_at->toISOString(),
                    ] : null,
                    'farm' => $rec->farm ? [
                        'id' => $rec->farm->id,
                        'farm_name' => $rec->farm->farm_name,
                        'district' => $rec->farm->district,
                        'upazila' => $rec->farm->upazila,
                        'address' => $rec->farm->address,
                        'pond_count' => $rec->farm->pond_count,
                        'total_water_area_decimals' => $rec->farm->total_water_area_decimals,
                    ] : null,
                    'farmer' => $rec->farm?->user ? [
                        'id' => $rec->farm->user->id,
                        'name' => $rec->farm->user->name,
                        'phone' => $rec->farm->user->phone,
                    ] : null,
                ];
            });

            $items = $items->concat($consultantRecords);
        }

        // Collect available districts for filtering
        $allDistricts = $items->pluck('farm.district')->filter()->unique()->values();

        // Apply Search Filter
        if ($search) {
            $lowerSearch = mb_strtolower($search);
            $items = $items->filter(function ($item) use ($lowerSearch) {
                $farmName = mb_strtolower($item['farm']['farm_name'] ?? '');
                $farmerName = mb_strtolower($item['farmer']['name'] ?? '');
                $farmerPhone = $item['farmer']['phone'] ?? '';
                $summary = mb_strtolower($item['summary'] ?? '');
                $findings = mb_strtolower($item['findings'] ?? '');
                $treatment = mb_strtolower($item['treatment'] ?? '');
                $recommendation = mb_strtolower($item['recommendation'] ?? '');
                $upazila = mb_strtolower($item['farm']['upazila'] ?? '');

                return str_contains($farmName, $lowerSearch)
                    || str_contains($farmerName, $lowerSearch)
                    || str_contains($farmerPhone, $lowerSearch)
                    || str_contains($summary, $lowerSearch)
                    || str_contains($findings, $lowerSearch)
                    || str_contains($treatment, $lowerSearch)
                    || str_contains($recommendation, $lowerSearch)
                    || str_contains($upazila, $lowerSearch);
            });
        }

        // Apply District Filter
        if ($district) {
            $items = $items->filter(function ($item) use ($district) {
                return ($item['farm']['district'] ?? '') === $district;
            });
        }

        // Date boundaries
        $today = Carbon::today();
        $endOfWeek = Carbon::today()->endOfWeek(); // End of current calendar week
        $endOfNextTwoWeeks = $endOfWeek->copy()->addWeeks(2);

        $groups = [
            'overdue' => collect(),
            'today' => collect(),
            'this_week' => collect(),
            'next_2_weeks' => collect(),
            'later' => collect(),
        ];

        foreach ($items as $item) {
            $followUpDate = Carbon::parse($item['next_follow_up'])->startOfDay();

            if ($followUpDate->lt($today)) {
                $item['group'] = 'overdue';
                $groups['overdue']->push($item);
            } elseif ($followUpDate->equalTo($today)) {
                $item['group'] = 'today';
                $groups['today']->push($item);
            } elseif ($followUpDate->lte($endOfWeek)) {
                $item['group'] = 'this_week';
                $groups['this_week']->push($item);
            } elseif ($followUpDate->lte($endOfNextTwoWeeks)) {
                $item['group'] = 'next_2_weeks';
                $groups['next_2_weeks']->push($item);
            } else {
                $item['group'] = 'later';
                $groups['later']->push($item);
            }
        }

        // Sort items inside each group
        $sortedGroups = [
            'overdue' => $groups['overdue']->sortBy('next_follow_up')->values(),
            'today' => $groups['today']->sortBy('next_follow_up')->values(),
            'this_week' => $groups['this_week']->sortBy('next_follow_up')->values(),
            'next_2_weeks' => $groups['next_2_weeks']->sortBy('next_follow_up')->values(),
            'later' => $groups['later']->sortBy('next_follow_up')->values(),
        ];

        $counts = [
            'total' => $items->count(),
            'overdue_and_today' => $sortedGroups['overdue']->count() + $sortedGroups['today']->count(),
            'overdue' => $sortedGroups['overdue']->count(),
            'today' => $sortedGroups['today']->count(),
            'this_week' => $sortedGroups['this_week']->count(),
            'next_2_weeks' => $sortedGroups['next_2_weeks']->count(),
            'later' => $sortedGroups['later']->count(),
        ];

        return $this->successResponse([
            'counts' => $counts,
            'groups' => $sortedGroups,
            'districts' => $allDistricts,
        ], 'Upcoming follow-ups retrieved successfully');
    }

    /**
     * Lightweight badge count of due-today + overdue follow-ups.
     */
    public function count(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasAnyRole(['veterinary_doctor', 'consultant', 'admin'])) {
            return $this->successResponse(['overdue_and_today_count' => 0]);
        }

        $today = Carbon::today()->toDateString();

        $vetCount = VetRecord::where('vet_id', $user->id)
            ->whereNotNull('next_follow_up')
            ->whereDate('next_follow_up', '<=', $today)
            ->count();

        $consultantCount = ConsultantRecord::where('consultant_id', $user->id)
            ->whereNotNull('next_follow_up')
            ->whereDate('next_follow_up', '<=', $today)
            ->count();

        return $this->successResponse([
            'overdue_and_today_count' => $vetCount + $consultantCount,
        ], 'Due follow-ups count retrieved successfully');
    }

    /**
     * Submit a follow-up reschedule request requiring admin approval.
     */
    public function reschedule(Request $request, string $type, int $id): JsonResponse
    {
        $user = $request->user();

        if (! in_array($type, ['vet', 'consultant'], true)) {
            return $this->errorResponse('Invalid record type. Must be "vet" or "consultant".', 400);
        }

        $validated = $request->validate([
            'new_date' => 'required|date_format:Y-m-d|after_or_equal:today',
            'reason' => 'required|string|min:3|max:1000',
        ]);

        $record = null;
        if ($type === 'vet') {
            $record = VetRecord::with(['farm.user', 'farm'])->find($id);
            if (! $record) {
                return $this->errorResponse('Veterinary record not found.', 404);
            }
            if (! $user->hasRole('admin') && $record->vet_id !== $user->id) {
                return $this->errorResponse('Unauthorized to reschedule this follow-up.', 403);
            }
        } else {
            $record = ConsultantRecord::with(['farm.user', 'farm'])->find($id);
            if (! $record) {
                return $this->errorResponse('Consultant record not found.', 404);
            }
            if (! $user->hasRole('admin') && $record->consultant_id !== $user->id) {
                return $this->errorResponse('Unauthorized to reschedule this follow-up.', 403);
            }
        }

        if (! $record->next_follow_up) {
            return $this->errorResponse('This visit record does not have a scheduled follow-up date.', 422);
        }

        $oldDate = $record->next_follow_up->toDateString();
        $newDate = $validated['new_date'];
        $reason = trim($validated['reason']);

        if ($oldDate === $newDate) {
            return $this->errorResponse('The proposed new date must be different from the currently scheduled date.', 422);
        }

        // Check if there is already a pending reschedule request
        $existingPending = RescheduleRequest::where('record_type', $type)
            ->where('record_id', $record->id)
            ->where('status', 'pending')
            ->first();

        if ($existingPending) {
            return $this->errorResponse('A reschedule request is already pending administrator approval for this follow-up.', 422);
        }

        // Create pending request
        $rescheduleRequest = RescheduleRequest::create([
            'record_type' => $type,
            'record_id' => $record->id,
            'farm_id' => $record->farm_id,
            'farmer_id' => $record->farm?->user_id,
            'practitioner_id' => $user->id,
            'old_date' => $oldDate,
            'new_date' => $newDate,
            'reason' => $reason,
            'status' => 'pending',
        ]);

        // Audit Log
        ActivityLog::log(
            'follow_up.reschedule_requested',
            $rescheduleRequest,
            [
                'reschedule_request_id' => $rescheduleRequest->id,
                'record_type' => $type,
                'record_id' => $record->id,
                'old_date' => $oldDate,
                'new_date' => $newDate,
                'reason' => $reason,
                'practitioner_id' => $user->id,
                'practitioner_name' => $user->name,
                'farm_name' => $record->farm?->farm_name,
            ],
            $user
        );

        // Notify Admins
        AdminNotification::notify(
            'follow_up.reschedule_requested',
            "Reschedule Request: {$record->farm?->farm_name}",
            "{$user->name} requested to reschedule follow-up for {$record->farm?->farm_name} from {$oldDate} to {$newDate}. Reason: {$reason}",
            [
                'reschedule_request_id' => $rescheduleRequest->id,
                'record_id' => $record->id,
                'record_type' => $type,
                'farm_id' => $record->farm_id,
                'action_url' => "/admin/reschedule-requests?highlight={$rescheduleRequest->id}",
                'old_date' => $oldDate,
                'new_date' => $newDate,
                'reason' => $reason,
                'practitioner_id' => $user->id,
                'practitioner_name' => $user->name,
            ],
            null
        );

        return $this->successResponse([
            'reschedule_request' => $rescheduleRequest,
            'record_id' => $record->id,
            'type' => $type,
            'old_date' => $oldDate,
            'new_date' => $newDate,
            'status' => 'pending',
        ], 'Reschedule request submitted successfully and is pending administrator approval.');
    }
}
