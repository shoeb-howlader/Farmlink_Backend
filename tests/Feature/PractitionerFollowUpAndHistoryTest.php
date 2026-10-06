<?php

use App\Models\ActivityLog;
use App\Models\AdminNotification;
use App\Models\Farm;
use App\Models\RescheduleRequest;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\VetRecord;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('assigned service requests hides completed items older than 48 hours', function () {
    $vet = User::factory()->veterinaryDoctor()->create();
    $farmer = User::factory()->farmer()->create();
    $farm = Farm::factory()->create(['user_id' => $farmer->id]);

    // Active request
    $activeSr = ServiceRequest::factory()->create([
        'farmer_id' => $farmer->id,
        'farm_id' => $farm->id,
        'assigned_to' => $vet->id,
        'status' => 'assigned',
        'type' => 'vet',
    ]);

    // Recent completed request (< 48h)
    $recentCompletedSr = ServiceRequest::factory()->create([
        'farmer_id' => $farmer->id,
        'farm_id' => $farm->id,
        'assigned_to' => $vet->id,
        'status' => 'completed',
        'type' => 'vet',
        'completed_at' => now()->subHours(6),
    ]);

    // Old completed request (> 48h)
    $oldCompletedSr = ServiceRequest::factory()->create([
        'farmer_id' => $farmer->id,
        'farm_id' => $farm->id,
        'assigned_to' => $vet->id,
        'status' => 'completed',
        'type' => 'vet',
        'completed_at' => now()->subHours(72),
    ]);

    Sanctum::actingAs($vet);

    // Filter "all"
    $responseAll = $this->getJson('/api/v1/my/service-requests?status=all');
    $responseAll->assertOk();
    $allIds = collect($responseAll->json('data.data'))->pluck('id')->all();

    expect($allIds)->toContain($activeSr->id);
    expect($allIds)->toContain($recentCompletedSr->id);
    expect($allIds)->not->toContain($oldCompletedSr->id);
    expect($responseAll->json('data.counts.completed'))->toBe(1);

    // Filter "completed"
    $responseCompleted = $this->getJson('/api/v1/my/service-requests?status=completed');
    $responseCompleted->assertOk();
    $completedIds = collect($responseCompleted->json('data.data'))->pluck('id')->all();

    expect($completedIds)->toContain($recentCompletedSr->id);
    expect($completedIds)->not->toContain($oldCompletedSr->id);
});

test('history endpoint returns all records permanently', function () {
    $vet = User::factory()->veterinaryDoctor()->create();
    $farmer = User::factory()->farmer()->create();
    $farm = Farm::factory()->create(['user_id' => $farmer->id]);

    VetRecord::factory()->create([
        'vet_id' => $vet->id,
        'farm_id' => $farm->id,
        'visit_date' => now()->subDays(10)->toDateString(),
        'next_follow_up' => now()->subDays(5)->toDateString(),
        'findings' => 'Past issue',
    ]);

    VetRecord::factory()->create([
        'vet_id' => $vet->id,
        'farm_id' => $farm->id,
        'visit_date' => now()->subDays(2)->toDateString(),
        'next_follow_up' => now()->addDays(7)->toDateString(),
        'findings' => 'Parasite checkup',
    ]);

    Sanctum::actingAs($vet);

    $response = $this->getJson('/api/v1/my/history');
    $response->assertOk();

    // All records exist in records list
    expect(count($response->json('data.records')))->toBe(2);
});

test('practitioner reschedule request creates pending approval request and does not change follow-up date immediately', function () {
    $vet = User::factory()->veterinaryDoctor()->create();
    $farmer = User::factory()->farmer()->create();
    $farm = Farm::factory()->create(['user_id' => $farmer->id]);

    $originalDate = now()->addDays(3)->toDateString();
    $newDate = now()->addDays(8)->toDateString();

    $record = VetRecord::factory()->create([
        'vet_id' => $vet->id,
        'farm_id' => $farm->id,
        'visit_date' => now()->subDays(2)->toDateString(),
        'next_follow_up' => $originalDate,
        'findings' => 'Fin rot treatment follow-up',
    ]);

    Sanctum::actingAs($vet);

    // Missing reason fails validation
    $badResponse = $this->postJson("/api/v1/my/follow-ups/vet/{$record->id}/reschedule", [
        'new_date' => $newDate,
        'reason' => '',
    ]);
    $badResponse->assertStatus(422)->assertJsonValidationErrors(['reason']);

    // Valid reschedule request
    $response = $this->postJson("/api/v1/my/follow-ups/vet/{$record->id}/reschedule", [
        'new_date' => $newDate,
        'reason' => 'Farmer requested postponement due to pond harvesting schedule.',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.old_date', $originalDate)
        ->assertJsonPath('data.new_date', $newDate);

    // Verify record date has NOT changed yet!
    $record->refresh();
    expect($record->next_follow_up->toDateString())->toBe($originalDate);

    // Verify pending RescheduleRequest in database
    $pendingReq = RescheduleRequest::where('record_type', 'vet')
        ->where('record_id', $record->id)
        ->first();
    expect($pendingReq)->not->toBeNull();
    expect($pendingReq->status)->toBe('pending');
    expect($pendingReq->new_date->toDateString())->toBe($newDate);
    expect($pendingReq->reason)->toBe('Farmer requested postponement due to pond harvesting schedule.');

    // Verify audit log
    $activityLog = ActivityLog::where('action', 'follow_up.reschedule_requested')->first();
    expect($activityLog)->not->toBeNull();

    // Verify admin notification
    $notification = AdminNotification::where('type', 'follow_up.reschedule_requested')->first();
    expect($notification)->not->toBeNull();
    expect($notification->data['reschedule_request_id'])->toBe($pendingReq->id);

    // Submitting again while pending should fail
    $dupResponse = $this->postJson("/api/v1/my/follow-ups/vet/{$record->id}/reschedule", [
        'new_date' => now()->addDays(9)->toDateString(),
        'reason' => 'Another attempt',
    ]);
    $dupResponse->assertStatus(422);
});

test('admin can approve reschedule request which updates record date and linked service request channel', function () {
    $admin = User::factory()->admin()->create();
    $vet = User::factory()->veterinaryDoctor()->create();
    $farmer = User::factory()->farmer()->create();
    $farm = Farm::factory()->create(['user_id' => $farmer->id]);

    $originalDate = now()->addDays(3)->toDateString();
    $newDate = now()->addDays(8)->toDateString();

    $record = VetRecord::factory()->create([
        'vet_id' => $vet->id,
        'farm_id' => $farm->id,
        'visit_date' => now()->subDays(2)->toDateString(),
        'next_follow_up' => $originalDate,
        'findings' => 'Parasite treatment review',
    ]);

    // Linked follow-up service request
    $serviceRequest = ServiceRequest::factory()->create([
        'farmer_id' => $farmer->id,
        'farm_id' => $farm->id,
        'type' => 'vet',
        'status' => 'pending',
        'source_channel' => 'system_generated',
        'parent_record_type' => VetRecord::class,
        'parent_record_id' => $record->id,
    ]);

    $rescheduleReq = RescheduleRequest::create([
        'record_type' => 'vet',
        'record_id' => $record->id,
        'farm_id' => $farm->id,
        'farmer_id' => $farmer->id,
        'practitioner_id' => $vet->id,
        'old_date' => $originalDate,
        'new_date' => $newDate,
        'reason' => 'Water salinity too low for current treatment.',
        'status' => 'pending',
    ]);

    Sanctum::actingAs($admin);

    $response = $this->patchJson("/api/v1/admin/reschedule-requests/{$rescheduleReq->id}/approve");
    $response->assertOk()
        ->assertJsonPath('data.new_date', $newDate);

    // Verify record dates
    $record->refresh();
    expect($record->next_follow_up->toDateString())->toBe($newDate);
    expect($record->original_follow_up_date->toDateString())->toBe($originalDate);
    expect($record->rescheduled_reason)->toBe('Water salinity too low for current treatment.');

    // Verify linked service request source_channel updated to follow_up_rescheduled!
    $serviceRequest->refresh();
    expect($serviceRequest->source_channel)->toBe('follow_up_rescheduled');

    // Verify request status
    $rescheduleReq->refresh();
    expect($rescheduleReq->status)->toBe('approved');
    expect($rescheduleReq->reviewed_by)->toBe($admin->id);

    // Verify notification to practitioner
    $practitionerNotif = AdminNotification::where('user_id', $vet->id)
        ->where('type', 'follow_up.reschedule_approved')
        ->first();
    expect($practitionerNotif)->not->toBeNull();

    // Verify notification to farmer
    $farmerNotif = AdminNotification::where('user_id', $farmer->id)
        ->where('type', 'follow_up.rescheduled')
        ->first();
    expect($farmerNotif)->not->toBeNull();
});

test('admin can reject reschedule request with note leaving original date intact', function () {
    $admin = User::factory()->admin()->create();
    $vet = User::factory()->veterinaryDoctor()->create();
    $farmer = User::factory()->farmer()->create();
    $farm = Farm::factory()->create(['user_id' => $farmer->id]);

    $originalDate = now()->addDays(3)->toDateString();
    $newDate = now()->addDays(12)->toDateString();

    $record = VetRecord::factory()->create([
        'vet_id' => $vet->id,
        'farm_id' => $farm->id,
        'visit_date' => now()->subDays(2)->toDateString(),
        'next_follow_up' => $originalDate,
    ]);

    $rescheduleReq = RescheduleRequest::create([
        'record_type' => 'vet',
        'record_id' => $record->id,
        'farm_id' => $farm->id,
        'farmer_id' => $farmer->id,
        'practitioner_id' => $vet->id,
        'old_date' => $originalDate,
        'new_date' => $newDate,
        'reason' => 'Practitioner attending seminar.',
        'status' => 'pending',
    ]);

    Sanctum::actingAs($admin);

    $response = $this->patchJson("/api/v1/admin/reschedule-requests/{$rescheduleReq->id}/reject", [
        'admin_note' => 'Antibiotic cycle must be checked within 5 days.',
    ]);
    $response->assertOk();

    // Verify record date is UNCHANGED
    $record->refresh();
    expect($record->next_follow_up->toDateString())->toBe($originalDate);

    // Verify request status & note
    $rescheduleReq->refresh();
    expect($rescheduleReq->status)->toBe('rejected');
    expect($rescheduleReq->admin_note)->toBe('Antibiotic cycle must be checked within 5 days.');

    // Verify notification to practitioner
    $practitionerNotif = AdminNotification::where('user_id', $vet->id)
        ->where('type', 'follow_up.reschedule_rejected')
        ->first();
    expect($practitionerNotif)->not->toBeNull();
});

test('my follow-ups returns grouped entries with 80+ records spanning Overdue, Today, This Week, Next 2 Weeks, Later', function () {
    $vet = User::factory()->veterinaryDoctor()->create();
    $farmer = User::factory()->farmer()->create();
    $farmKhulna = Farm::factory()->create(['user_id' => $farmer->id, 'district' => 'Khulna']);
    $farmSatkhira = Farm::factory()->create(['user_id' => $farmer->id, 'district' => 'Satkhira']);

    $today = now()->startOfDay();
    $endOfWeek = now()->endOfWeek()->startOfDay();

    // 15 Overdue records
    for ($i = 1; $i <= 15; $i++) {
        VetRecord::factory()->create([
            'vet_id' => $vet->id,
            'farm_id' => $farmKhulna->id,
            'visit_date' => now()->subDays(20)->toDateString(),
            'next_follow_up' => now()->subDays($i)->toDateString(),
            'findings' => "Overdue item #{$i}",
        ]);
    }

    // 10 Today records
    for ($i = 1; $i <= 10; $i++) {
        VetRecord::factory()->create([
            'vet_id' => $vet->id,
            'farm_id' => $farmKhulna->id,
            'visit_date' => now()->subDays(5)->toDateString(),
            'next_follow_up' => now()->toDateString(),
            'findings' => "Today item #{$i}",
        ]);
    }

    // 20 This Week records (between tomorrow and end of week, or +1..+3 days)
    $thisWeekDate = $endOfWeek->isAfter($today) ? $today->copy()->addDays(1) : $today;
    for ($i = 1; $i <= 20; $i++) {
        VetRecord::factory()->create([
            'vet_id' => $vet->id,
            'farm_id' => $farmSatkhira->id,
            'visit_date' => now()->subDays(3)->toDateString(),
            'next_follow_up' => $thisWeekDate->toDateString(),
            'findings' => "This Week item #{$i}",
        ]);
    }

    // 20 Next 2 Weeks records
    $nextTwoWeeksDate = $endOfWeek->copy()->addDays(5);
    for ($i = 1; $i <= 20; $i++) {
        VetRecord::factory()->create([
            'vet_id' => $vet->id,
            'farm_id' => $farmKhulna->id,
            'visit_date' => now()->subDays(2)->toDateString(),
            'next_follow_up' => $nextTwoWeeksDate->toDateString(),
            'findings' => "Next 2 Weeks item #{$i}",
        ]);
    }

    // 20 Later records (> 2 weeks)
    $laterDate = $endOfWeek->copy()->addDays(20);
    for ($i = 1; $i <= 20; $i++) {
        VetRecord::factory()->create([
            'vet_id' => $vet->id,
            'farm_id' => $farmSatkhira->id,
            'visit_date' => now()->subDays(1)->toDateString(),
            'next_follow_up' => $laterDate->toDateString(),
            'findings' => "Later item #{$i}",
        ]);
    }

    Sanctum::actingAs($vet);

    // Call /my/follow-ups
    $response = $this->getJson('/api/v1/my/follow-ups');
    $response->assertOk();

    $data = $response->json('data');
    expect($data['counts']['total'])->toBe(85);
    expect($data['counts']['overdue'])->toBe(15);
    expect($data['counts']['today'])->toBe(10);
    expect($data['counts']['overdue_and_today'])->toBe(25);
    expect($data['counts']['this_week'])->toBe(20);
    expect($data['counts']['next_2_weeks'])->toBe(20);
    expect($data['counts']['later'])->toBe(20);

    // Verify groups exist and contain items
    expect(count($data['groups']['overdue']))->toBe(15);
    expect(count($data['groups']['today']))->toBe(10);
    expect(count($data['groups']['this_week']))->toBe(20);
    expect(count($data['groups']['next_2_weeks']))->toBe(20);
    expect(count($data['groups']['later']))->toBe(20);

    // Test district filter
    $filteredResponse = $this->getJson('/api/v1/my/follow-ups?district=Satkhira');
    $filteredResponse->assertOk();
    $satkhiraTotal = $filteredResponse->json('data.counts.total');
    expect($satkhiraTotal)->toBe(40); // 20 this week + 20 later

    // Test search filter
    $searchResponse = $this->getJson('/api/v1/my/follow-ups?search=Overdue');
    $searchResponse->assertOk();
    expect($searchResponse->json('data.counts.total'))->toBe(15);

    // Test count endpoint
    $countResponse = $this->getJson('/api/v1/my/follow-ups/count');
    $countResponse->assertOk();
    expect($countResponse->json('data.overdue_and_today_count'))->toBe(25);
});

test('admin reschedule requests queue and count endpoints work', function () {
    $admin = User::factory()->admin()->create();
    $vet = User::factory()->veterinaryDoctor()->create();
    $farmer = User::factory()->farmer()->create();
    $farm = Farm::factory()->create(['user_id' => $farmer->id]);

    $rec1 = VetRecord::factory()->create(['vet_id' => $vet->id, 'farm_id' => $farm->id]);
    $rec2 = VetRecord::factory()->create(['vet_id' => $vet->id, 'farm_id' => $farm->id]);

    RescheduleRequest::create([
        'record_type' => 'vet',
        'record_id' => $rec1->id,
        'farm_id' => $farm->id,
        'farmer_id' => $farmer->id,
        'practitioner_id' => $vet->id,
        'old_date' => now()->addDays(2)->toDateString(),
        'new_date' => now()->addDays(5)->toDateString(),
        'reason' => 'Reason 1',
        'status' => 'pending',
    ]);

    RescheduleRequest::create([
        'record_type' => 'vet',
        'record_id' => $rec2->id,
        'farm_id' => $farm->id,
        'farmer_id' => $farmer->id,
        'practitioner_id' => $vet->id,
        'old_date' => now()->addDays(3)->toDateString(),
        'new_date' => now()->addDays(7)->toDateString(),
        'reason' => 'Reason 2',
        'status' => 'pending',
    ]);

    Sanctum::actingAs($admin);

    $countRes = $this->getJson('/api/v1/admin/reschedule-requests/count');
    $countRes->assertOk()->assertJsonPath('data.pending_count', 2);

    $indexRes = $this->getJson('/api/v1/admin/reschedule-requests');
    $indexRes->assertOk()
        ->assertJsonPath('data.pending_count', 2)
        ->assertJsonCount(2, 'data.requests');
});
