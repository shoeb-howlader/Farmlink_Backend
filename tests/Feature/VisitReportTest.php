<?php

use App\Models\Farm;
use App\Models\ServiceRequest;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->admin = User::factory()->admin()->create();
    $this->farmer = User::factory()->farmer()->create();
    $this->vet = User::factory()->veterinaryDoctor()->create();
    $this->consultant = User::factory()->consultant()->create();

    $this->farm = Farm::factory()->create([
        'user_id' => $this->farmer->id,
        'farm_name' => 'Bismillah Aqua Farm',
        'district' => 'Satkhira',
    ]);
});

test('vet can log visit with structured test results and prescription items', function () {
    $sr = ServiceRequest::factory()->create([
        'farm_id' => $this->farm->id,
        'farmer_id' => $this->farmer->id,
        'assigned_to' => $this->vet->id,
        'type' => 'vet',
        'status' => 'assigned',
    ]);

    Sanctum::actingAs($this->vet);

    $payload = [
        'visit_date' => now()->format('Y-m-d'),
        'findings' => 'Gill necrosis detected in pond 2, lethargic shrimp swim patterns.',
        'treatment' => 'Administer antibiotic in feed and apply disinfectant.',
        'service_request_id' => $sr->id,
        'test_results' => [
            [
                'parameter' => 'Dissolved Oxygen',
                'value' => '3.8',
                'unit' => 'mg/L',
                'reference_range' => '5.0 - 8.0',
                'flag' => 'low',
            ],
            [
                'parameter' => 'pH',
                'value' => '7.8',
                'unit' => '',
                'reference_range' => '7.5 - 8.5',
                'flag' => 'normal',
            ],
        ],
        'prescription_items' => [
            [
                'medicine_name' => 'Oxytetracycline 20%',
                'dosage' => '5g / kg feed',
                'frequency' => 'Twice daily',
                'duration' => '7 days',
                'instructions' => 'Top dress feed with binder oil.',
            ],
        ],
    ];

    $response = $this->postJson("/api/v1/farms/{$this->farm->id}/vet-records", $payload);

    $response->assertCreated();
    $response->assertJsonPath('data.findings', $payload['findings']);
    $response->assertJsonCount(2, 'data.test_results');
    $response->assertJsonPath('data.test_results.0.parameter', 'Dissolved Oxygen');
    $response->assertJsonPath('data.test_results.0.flag', 'low');

    $sr->refresh();
    expect($sr->status)->toBe('completed');
    expect($sr->fulfilled_record_id)->not->toBeNull();

    // Test downloading unified visit report as farmer
    Sanctum::actingAs($this->farmer);
    $reportRes = $this->get("/api/v1/service-requests/{$sr->id}/visit-report");

    $reportRes->assertOk();
    expect($reportRes->headers->get('content-type'))->toContain('application/pdf');
});

test('consultant can log visit with structured recommendations and test readings', function () {
    $sr = ServiceRequest::factory()->create([
        'farm_id' => $this->farm->id,
        'farmer_id' => $this->farmer->id,
        'assigned_to' => $this->consultant->id,
        'type' => 'consultant',
        'status' => 'assigned',
    ]);

    Sanctum::actingAs($this->consultant);

    $payload = [
        'visit_date' => now()->format('Y-m-d'),
        'recommendation' => 'Optimize paddlewheel aerator schedule and shift to 32% CP nursery pellet.',
        'service_request_id' => $sr->id,
        'test_results' => [
            [
                'parameter' => 'Ammonia (NH3)',
                'value' => '0.8',
                'unit' => 'ppm',
                'reference_range' => '< 0.1 ppm',
                'flag' => 'high',
            ],
        ],
        'recommendations' => [
            [
                'type' => 'feed',
                'item_name' => 'Grower Pellet 32% CP',
                'reasoning' => 'Switch to higher digestible protein to reduce organic load.',
            ],
            [
                'type' => 'practice_change',
                'item_name' => 'Night Aeration Run-Time Extension',
                'reasoning' => 'Run aerators from 11 PM to 6 AM uninterrupted.',
            ],
        ],
    ];

    $response = $this->postJson("/api/v1/farms/{$this->farm->id}/consultant-records", $payload);

    $response->assertCreated();
    $response->assertJsonPath('data.recommendation', $payload['recommendation']);
    $response->assertJsonCount(1, 'data.test_results');
    $response->assertJsonCount(2, 'data.recommendations');
    $response->assertJsonPath('data.recommendations.0.type', 'feed');

    $sr->refresh();
    expect($sr->status)->toBe('completed');

    // Test downloading visit report via service request as admin
    Sanctum::actingAs($this->admin);
    $reportRes = $this->get("/api/v1/service-requests/{$sr->id}/visit-report");

    $reportRes->assertOk();
    expect($reportRes->headers->get('content-type'))->toContain('application/pdf');
});

test('practitioner can upload visit photos and link them to clinical record', function () {
    \Illuminate\Support\Facades\Storage::fake('public');

    Sanctum::actingAs($this->vet);

    // 1. Upload photo
    $file = \Illuminate\Http\UploadedFile::fake()->image('pond_scum.jpg', 600, 400);
    $uploadRes = $this->postJson('/api/v1/visit-photos/upload', [
        'photo' => $file,
    ]);

    $uploadRes->assertOk()
        ->assertJsonStructure([
            'data' => ['photo_path', 'url', 'thumbnail_url'],
        ]);

    $photoPath = $uploadRes->json('data.photo_path');

    // 2. Log vet visit with photo
    $sr = ServiceRequest::factory()->create([
        'farm_id' => $this->farm->id,
        'farmer_id' => $this->farmer->id,
        'assigned_to' => $this->vet->id,
        'type' => 'vet',
        'status' => 'assigned',
    ]);

    $recordRes = $this->postJson("/api/v1/farms/{$this->farm->id}/vet-records", [
        'visit_date' => now()->format('Y-m-d'),
        'findings' => "Line 1: Severe fin erosion observed.\nLine 2: Mortalities in deep corner.",
        'treatment' => "Step 1: Drain 25% pond water.\nStep 2: Apply disinfectant.",
        'service_request_id' => $sr->id,
        'photos' => [
            [
                'photo_path' => $photoPath,
                'caption' => 'Pond 2 surface algal bloom',
                'sort_order' => 1,
            ],
        ],
    ]);

    $recordRes->assertCreated();
    $recordRes->assertJsonCount(1, 'data.photos');
    $recordRes->assertJsonPath('data.photos.0.caption', 'Pond 2 surface algal bloom');

    $this->assertDatabaseHas('visit_photos', [
        'photo_path' => $photoPath,
        'caption' => 'Pond 2 surface algal bloom',
    ]);

    // 3. Test previous visits endpoint
    $prevRes = $this->getJson("/api/v1/service-requests/{$sr->id}/previous-visits");
    $prevRes->assertOk();
    $prevRes->assertJsonCount(1, 'data');
    $prevRes->assertJsonPath('data.0.type', 'vet');
    $prevRes->assertJsonPath('data.0.photos_count', 1);
});
