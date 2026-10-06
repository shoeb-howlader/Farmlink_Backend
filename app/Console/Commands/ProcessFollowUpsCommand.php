<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\AdminNotification;
use App\Models\ConsultantRecord;
use App\Models\ServiceRequest;
use App\Models\VetRecord;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ProcessFollowUpsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'farmlink:process-follow-ups {--lead-days=2 : Number of lead days ahead to trigger service requests}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically create service requests for upcoming/due follow-ups and send reminders.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $leadDays = (int) $this->option('lead-days');
        $cutoffDate = Carbon::today()->addDays($leadDays);
        $today = Carbon::today();

        $this->info("Processing follow-up automation up to {$cutoffDate->toDateString()}...");

        $createdRequestsCount = 0;
        $leadRemindersCount = 0;
        $overdueRemindersCount = 0;

        // ══════════════════════════════════════════════════════════════════
        // 1. VET RECORDS AUTOMATION
        // ══════════════════════════════════════════════════════════════════
        $vetRecords = VetRecord::with(['farm.user', 'followUpServiceRequest', 'followUpRecords'])
            ->whereNotNull('next_follow_up')
            ->whereDate('next_follow_up', '<=', $cutoffDate)
            ->whereNull('follow_up_service_request_id')
            ->whereDoesntHave('followUpRecords')
            ->get();

        foreach ($vetRecords as $rec) {
            if (! $rec->farm || ! $rec->farm->user_id) {
                continue;
            }

            // Ensure no service request was already generated for this parent record
            $existingSr = ServiceRequest::where('parent_record_type', VetRecord::class)
                ->where('parent_record_id', $rec->id)
                ->first();

            if ($existingSr) {
                $rec->update(['follow_up_service_request_id' => $existingSr->id]);
                continue;
            }

            $diagnosisSnippet = Str::limit($rec->findings ?: 'Veterinary diagnosis', 100);
            $description = "Automated follow-up visit for veterinary treatment on {$rec->visit_date->format('d M Y')}. Diagnosis: {$diagnosisSnippet}. (Ref #VR-{$rec->id})";

            $isRescheduled = ! empty($rec->original_follow_up_date) || ! empty($rec->rescheduled_at);
            $channel = $isRescheduled ? 'follow_up_rescheduled' : 'system_generated';

            $sr = ServiceRequest::create([
                'farm_id' => $rec->farm_id,
                'farmer_id' => $rec->farm->user_id,
                'type' => 'vet',
                'description' => $description,
                'urgency' => 'normal',
                'status' => 'pending',
                'source_channel' => $channel,
                'parent_record_type' => VetRecord::class,
                'parent_record_id' => $rec->id,
            ]);

            $rec->update(['follow_up_service_request_id' => $sr->id]);
            $createdRequestsCount++;

            ActivityLog::log('service_request.follow_up_auto_created', $sr, [
                'origin_record_id' => $rec->id,
                'origin_record_type' => 'vet',
                'scheduled_for' => $rec->next_follow_up->toDateString(),
                'is_rescheduled' => $isRescheduled,
            ]);
        }

        // ══════════════════════════════════════════════════════════════════
        // 2. CONSULTANT RECORDS AUTOMATION
        // ══════════════════════════════════════════════════════════════════
        $consultantRecords = ConsultantRecord::with(['farm.user', 'followUpServiceRequest', 'followUpRecords'])
            ->whereNotNull('next_follow_up')
            ->whereDate('next_follow_up', '<=', $cutoffDate)
            ->whereNull('follow_up_service_request_id')
            ->whereDoesntHave('followUpRecords')
            ->get();

        foreach ($consultantRecords as $rec) {
            if (! $rec->farm || ! $rec->farm->user_id) {
                continue;
            }

            $existingSr = ServiceRequest::where('parent_record_type', ConsultantRecord::class)
                ->where('parent_record_id', $rec->id)
                ->first();

            if ($existingSr) {
                $rec->update(['follow_up_service_request_id' => $existingSr->id]);
                continue;
            }

            $recSnippet = Str::limit($rec->recommendation ?: 'Consultation advice', 100);
            $description = "Automated follow-up visit for aquaculture advisory on {$rec->visit_date->format('d M Y')}. Advisory: {$recSnippet}. (Ref #CR-{$rec->id})";

            $isRescheduled = ! empty($rec->original_follow_up_date) || ! empty($rec->rescheduled_at);
            $channel = $isRescheduled ? 'follow_up_rescheduled' : 'system_generated';

            $sr = ServiceRequest::create([
                'farm_id' => $rec->farm_id,
                'farmer_id' => $rec->farm->user_id,
                'type' => 'consultant',
                'description' => $description,
                'urgency' => 'normal',
                'status' => 'pending',
                'source_channel' => $channel,
                'parent_record_type' => ConsultantRecord::class,
                'parent_record_id' => $rec->id,
            ]);

            $rec->update(['follow_up_service_request_id' => $sr->id]);
            $createdRequestsCount++;

            ActivityLog::log('service_request.follow_up_auto_created', $sr, [
                'origin_record_id' => $rec->id,
                'origin_record_type' => 'consultant',
                'scheduled_for' => $rec->next_follow_up->toDateString(),
                'is_rescheduled' => $isRescheduled,
            ]);
        }

        // ══════════════════════════════════════════════════════════════════
        // 3. LEAD-TIME REMINDERS (Approaching within lead days)
        // ══════════════════════════════════════════════════════════════════
        $allUpcoming = collect()
            ->concat(VetRecord::with(['farm.user'])->whereNotNull('next_follow_up')
                ->whereDate('next_follow_up', '>=', $today)
                ->whereDate('next_follow_up', '<=', $cutoffDate)
                ->whereNull('lead_reminder_sent_at')
                ->get())
            ->concat(ConsultantRecord::with(['farm.user'])->whereNotNull('next_follow_up')
                ->whereDate('next_follow_up', '>=', $today)
                ->whereDate('next_follow_up', '<=', $cutoffDate)
                ->whereNull('lead_reminder_sent_at')
                ->get());

        foreach ($allUpcoming as $rec) {
            $farm = $rec->farm;
            if (! $farm) {
                continue;
            }

            $practitionerId = $rec->vet_id ?? $rec->consultant_id;
            $formattedDate = $rec->next_follow_up->format('d M Y');

            // Notify farmer
            if ($farm->user_id) {
                AdminNotification::notify(
                    'follow_up.approaching',
                    "Upcoming Follow-Up: {$farm->farm_name}",
                    "Your scheduled follow-up visit for {$farm->farm_name} is approaching on {$formattedDate}. A specialist has been queued.",
                    [
                        'farm_id' => $farm->id,
                        'record_id' => $rec->id,
                        'next_follow_up' => $rec->next_follow_up->toDateString(),
                    ],
                    $farm->user_id
                );
            }

            // Notify original practitioner
            if ($practitionerId) {
                AdminNotification::notify(
                    'follow_up.approaching_practitioner',
                    "Scheduled Follow-Up Due: {$farm->farm_name}",
                    "Follow-up visit for {$farm->farm_name} is scheduled for {$formattedDate} (Original visit on {$rec->visit_date->format('d M Y')}).",
                    [
                        'farm_id' => $farm->id,
                        'record_id' => $rec->id,
                        'next_follow_up' => $rec->next_follow_up->toDateString(),
                    ],
                    $practitionerId
                );
            }

            $rec->update(['lead_reminder_sent_at' => now()]);
            $leadRemindersCount++;
        }

        // ══════════════════════════════════════════════════════════════════
        // 4. OVERDUE REMINDERS (Follow-up date in the past, unfulfilled)
        // ══════════════════════════════════════════════════════════════════
        $allOverdue = collect()
            ->concat(VetRecord::with(['farm.user', 'followUpServiceRequest', 'followUpRecords'])
                ->whereNotNull('next_follow_up')
                ->whereDate('next_follow_up', '<', $today)
                ->whereNull('overdue_reminder_sent_at')
                ->whereDoesntHave('followUpRecords')
                ->where(function ($q) {
                    $q->whereNull('follow_up_service_request_id')
                        ->orWhereHas('followUpServiceRequest', fn ($srq) => $srq->where('status', '!=', 'completed'));
                })
                ->get())
            ->concat(ConsultantRecord::with(['farm.user', 'followUpServiceRequest', 'followUpRecords'])
                ->whereNotNull('next_follow_up')
                ->whereDate('next_follow_up', '<', $today)
                ->whereNull('overdue_reminder_sent_at')
                ->whereDoesntHave('followUpRecords')
                ->where(function ($q) {
                    $q->whereNull('follow_up_service_request_id')
                        ->orWhereHas('followUpServiceRequest', fn ($srq) => $srq->where('status', '!=', 'completed'));
                })
                ->get());

        foreach ($allOverdue as $rec) {
            $farm = $rec->farm;
            if (! $farm) {
                continue;
            }

            $practitionerId = $rec->vet_id ?? $rec->consultant_id;
            $formattedDate = $rec->next_follow_up->format('d M Y');

            // Notify farmer
            if ($farm->user_id) {
                AdminNotification::notify(
                    'follow_up.overdue',
                    "Overdue Follow-Up: {$farm->farm_name}",
                    "The scheduled follow-up visit for {$farm->farm_name} was due on {$formattedDate}. Please contact us or log in to request immediate assistance.",
                    [
                        'farm_id' => $farm->id,
                        'record_id' => $rec->id,
                        'next_follow_up' => $rec->next_follow_up->toDateString(),
                    ],
                    $farm->user_id
                );
            }

            // Notify practitioner
            if ($practitionerId) {
                AdminNotification::notify(
                    'follow_up.overdue_practitioner',
                    "Overdue Follow-Up Alert: {$farm->farm_name}",
                    "Follow-up visit for {$farm->farm_name} was due on {$formattedDate} and remains unfulfilled.",
                    [
                        'farm_id' => $farm->id,
                        'record_id' => $rec->id,
                        'next_follow_up' => $rec->next_follow_up->toDateString(),
                    ],
                    $practitionerId
                );
            }

            $rec->update(['overdue_reminder_sent_at' => now()]);
            $overdueRemindersCount++;
        }

        $this->info("Completed: {$createdRequestsCount} service requests created, {$leadRemindersCount} lead reminders sent, {$overdueRemindersCount} overdue reminders sent.");

        return Command::SUCCESS;
    }
}
