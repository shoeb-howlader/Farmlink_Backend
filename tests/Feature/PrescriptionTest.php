<?php

use App\Models\Farm;
use App\Models\Prescription;
use App\Models\Product;
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

test('vet can create a vet record with structured prescription items', function () {
    $vet = User::factory()->veterinaryDoctor()->create();
    $farmer = User::factory()->farmer()->create();
    $farm = Farm::factory()->create(['user_id' => $farmer->id]);
    $product = Product::factory()->create(['name' => 'AquaPro-B Probiotic']);

    Sanctum::actingAs($vet);

    $payload = [
        'visit_date' => now()->toDateString(),
        'findings' => 'Observed low dissolved oxygen and early signs of tail rot.',
        'next_follow_up' => now()->addDays(5)->toDateString(),
        'prescription_items' => [
            [
                'medicine_name' => 'AquaPro-B Probiotic',
                'product_id' => $product->id,
                'dosage' => '500g per acre',
                'frequency' => 'Once every 3 days',
                'duration' => '6 days',
                'instructions' => 'Dissolve in pond water before broadcasting.',
            ],
            [
                'medicine_name' => 'Oxytetracycline 20%',
                'dosage' => '10g / kg feed',
                'frequency' => 'Twice daily',
                'duration' => '5 days',
                'instructions' => 'Mix thoroughly with feed pellets.',
            ],
        ],
    ];

    $response = $this->postJson("/api/v1/farms/{$farm->id}/vet-records", $payload);

    $response->assertStatus(201)
        ->assertJsonPath('data.findings', 'Observed low dissolved oxygen and early signs of tail rot.')
        ->assertJsonPath('data.prescription.items.0.medicine_name', 'AquaPro-B Probiotic')
        ->assertJsonPath('data.prescription.items.0.product_id', $product->id)
        ->assertJsonPath('data.prescription.items.1.medicine_name', 'Oxytetracycline 20%');

    $this->assertDatabaseHas('vet_records', [
        'farm_id' => $farm->id,
        'vet_id' => $vet->id,
    ]);

    $this->assertDatabaseHas('prescriptions', [
        'vet_record_id' => $response->json('data.id'),
    ]);

    $this->assertDatabaseHas('prescription_items', [
        'medicine_name' => 'AquaPro-B Probiotic',
        'product_id' => $product->id,
    ]);
});

test('farmer can download prescription PDF for their farm visit', function () {
    $farmer = User::factory()->farmer()->create();
    $vet = User::factory()->veterinaryDoctor()->create();
    $farm = Farm::factory()->create(['user_id' => $farmer->id]);

    $record = VetRecord::factory()->create([
        'farm_id' => $farm->id,
        'vet_id' => $vet->id,
    ]);

    $prescription = Prescription::create(['vet_record_id' => $record->id]);
    $prescription->items()->create([
        'medicine_name' => 'Oxytetracycline 20%',
        'dosage' => '10g / kg feed',
        'frequency' => 'Twice daily',
        'duration' => '5 days',
        'instructions' => 'Mix with feed.',
    ]);

    Sanctum::actingAs($farmer);

    $response = $this->get("/api/v1/prescriptions/{$prescription->id}/pdf");

    $response->assertStatus(200);
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

test('unauthorized user cannot download prescription PDF', function () {
    $farmer = User::factory()->farmer()->create();
    $intruder = User::factory()->farmer()->create();
    $vet = User::factory()->veterinaryDoctor()->create();
    $farm = Farm::factory()->create(['user_id' => $farmer->id]);

    $record = VetRecord::factory()->create([
        'farm_id' => $farm->id,
        'vet_id' => $vet->id,
    ]);

    $prescription = Prescription::create(['vet_record_id' => $record->id]);

    Sanctum::actingAs($intruder);

    $response = $this->get("/api/v1/prescriptions/{$prescription->id}/pdf");

    $response->assertStatus(403);
});

test('admin can download any prescription PDF', function () {
    $admin = User::factory()->admin()->create();
    $farmer = User::factory()->farmer()->create();
    $vet = User::factory()->veterinaryDoctor()->create();
    $farm = Farm::factory()->create(['user_id' => $farmer->id]);

    $record = VetRecord::factory()->create([
        'farm_id' => $farm->id,
        'vet_id' => $vet->id,
    ]);

    $prescription = Prescription::create(['vet_record_id' => $record->id]);
    $prescription->items()->create([
        'medicine_name' => 'Oxytetracycline 20%',
        'dosage' => '10g / kg feed',
    ]);

    Sanctum::actingAs($admin);

    $response = $this->get("/api/v1/prescriptions/{$prescription->id}/pdf");

    $response->assertStatus(200);
});

test('omitted medicine_given is auto-summarized from prescription items', function () {
    $vet = User::factory()->veterinaryDoctor()->create();
    $farmer = User::factory()->farmer()->create();
    $farm = Farm::factory()->create(['user_id' => $farmer->id]);

    Sanctum::actingAs($vet);

    $payload = [
        'visit_date' => now()->toDateString(),
        'findings' => 'Regular pond sampling checkup.',
        'prescription_items' => [
            [
                'medicine_name' => 'BKC 80%',
                'dosage' => '50ml / decimal',
            ],
            [
                'medicine_name' => 'Vitamin C Feed Supplement',
                'dosage' => '5g / kg',
            ],
        ],
    ];

    $response = $this->postJson("/api/v1/farms/{$farm->id}/vet-records", $payload);

    $response->assertStatus(201);
    expect($response->json('data.medicine_given'))->toContain('BKC 80% (50ml / decimal)');
    expect($response->json('data.medicine_given'))->toContain('Vitamin C Feed Supplement (5g / kg)');
});
