<?php

use App\Models\Farm;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\VetRecord;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('a record with next_follow_up = today generates exactly one new service_request via scheduled job and no duplicates on repeated runs', function () {
    $vet = User::factory()->veterinaryDoctor()->create();
    $farmer = User::factory()->farmer()->create();
    $farm = Farm::factory()->create(['user_id' => $farmer->id]);

    $record = VetRecord::factory()->create([
        'farm_id' => $farm->id,
        'vet_id' => $vet->id,
        'visit_date' => now()->subDays(7)->toDateString(),
        'findings' => 'Bacterial gill problem treated.',
        'next_follow_up' => now()->toDateString(),
    ]);

    expect(ServiceRequest::count())->toBe(0);

    // 1st Run
    Artisan::call('farmlink:process-follow-ups');

    expect(ServiceRequest::count())->toBe(1);
    $sr = ServiceRequest::first();
    expect($sr->farm_id)->toBe($farm->id)
        ->and($sr->farmer_id)->toBe($farmer->id)
        ->and($sr->type)->toBe('vet')
        ->and($sr->source_channel)->toBe('system_generated')
        ->and($sr->parent_record_type)->toBe(VetRecord::class)
        ->and($sr->parent_record_id)->toBe($record->id)
        ->and($sr->status)->toBe('pending');

    $record->refresh();
    expect($record->follow_up_service_request_id)->toBe($sr->id)
        ->and($record->lead_reminder_sent_at)->not->toBeNull();

    // 2nd Run (Repeated on the same day)
    Artisan::call('farmlink:process-follow-ups');

    // Must still be exactly 1, no duplicate
    expect(ServiceRequest::count())->toBe(1);

    // 3rd Run
    Artisan::call('farmlink:process-follow-ups');
    expect(ServiceRequest::count())->toBe(1);
});

test('service request metrics include follow-up completion rate', function () {
    $admin = User::factory()->admin()->create();
    Sanctum::actingAs($admin);

    $response = $this->getJson('/api/v1/admin/service-requests/metrics');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'data' => [
                'open_requests',
                'urgent_requests',
                'completed_this_month',
                'avg_turnaround_hours',
                'follow_up_completion_rate',
                'scheduled_follow_ups_count',
                'completed_follow_ups_count',
            ],
        ]);
});
