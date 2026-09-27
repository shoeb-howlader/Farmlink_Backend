<?php

namespace Tests\Feature;

use App\Models\ConsultantRecord;
use App\Models\Farm;
use App\Models\User;
use App\Models\VetRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SeederTextUniquenessTest extends TestCase
{
    use RefreshDatabase;

    public function test_consultant_and_vet_factories_generate_unique_texts(): void
    {
        Role::firstOrCreate(['name' => 'veterinary_doctor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'consultant', 'guard_name' => 'web']);

        $vet = User::factory()->create();
        $vet->assignRole('veterinary_doctor');
        $consultant = User::factory()->create();
        $consultant->assignRole('consultant');

        $farm = Farm::factory()->create();

        // Generate 25 records each
        $consultantRecords = ConsultantRecord::factory()->count(25)->create([
            'farm_id' => $farm->id,
            'consultant_id' => $consultant->id,
        ]);

        $vetRecords = VetRecord::factory()->count(25)->create([
            'farm_id' => $farm->id,
            'vet_id' => $vet->id,
        ]);

        $recTexts = $consultantRecords->pluck('recommendation');
        $this->assertCount(25, $recTexts->unique(), 'All seeded consultant recommendations must be unique with zero duplicates.');

        $findingsTexts = $vetRecords->pluck('findings');
        $this->assertCount(25, $findingsTexts->unique(), 'All seeded vet clinical findings must be unique with zero duplicates.');
    }
}
