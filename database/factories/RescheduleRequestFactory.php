<?php

namespace Database\Factories;

use App\Models\Farm;
use App\Models\RescheduleRequest;
use App\Models\User;
use App\Models\VetRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RescheduleRequest>
 */
class RescheduleRequestFactory extends Factory
{
    protected $model = RescheduleRequest::class;

    public function definition(): array
    {
        return [
            'record_type' => 'vet',
            'record_id' => VetRecord::factory(),
            'farm_id' => Farm::factory(),
            'farmer_id' => User::factory()->farmer(),
            'practitioner_id' => User::factory()->veterinaryDoctor(),
            'old_date' => now()->addDays(3)->toDateString(),
            'new_date' => now()->addDays(8)->toDateString(),
            'reason' => 'Farmer requested postponement due to harvest schedule.',
            'status' => 'pending',
            'admin_note' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'reviewed_by' => User::factory()->admin(),
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
            'admin_note' => 'Treatment interval cannot exceed 7 days.',
            'reviewed_by' => User::factory()->admin(),
            'reviewed_at' => now(),
        ]);
    }
}
