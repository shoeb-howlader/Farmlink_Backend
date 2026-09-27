<?php

namespace Database\Factories;

use App\Models\Farm;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceRequest>
 */
class ServiceRequestFactory extends Factory
{
    protected $model = ServiceRequest::class;

    public function definition(): array
    {
        $descriptions = [
            'White spot symptoms observed in Pond #2. Urgent veterinary diagnosis and treatment plan required.',
            'Shrimp are swimming sluggishly near water surface and refusing feed trays during morning feeding.',
            'Advisory requested for post-larvae stocking density, water salinity management, and nursery preparation.',
            'Discolored gills and slow feeding behavior noticed in nursery pond after continuous rainfall.',
            'Consultant guidance requested on aerator placement and water exchange schedule for semi-intensive culture.',
            'Suspected bacterial necrosis causing tail rot in juvenile stock. Specialist visit needed.',
            'Water transparency dropped below 20cm with dense green algae bloom. Need immediate remediation advisory.',
            'Assistance needed to inspect dissolved oxygen levels and check hepatopancreas condition in pond #1.',
        ];

        return [
            'farm_id' => Farm::factory(),
            'farmer_id' => User::factory(),
            'type' => fake()->randomElement(['vet', 'consultant']),
            'description' => fake()->randomElement($descriptions),
            'urgency' => fake()->randomElement(['normal', 'urgent']),
            'status' => 'pending',
            'assigned_to' => null,
            'assigned_at' => null,
            'completed_at' => null,
            'fulfilled_record_type' => null,
            'fulfilled_record_id' => null,
            'rating' => null,
            'feedback_note' => null,
        ];
    }

    public function urgent(): static
    {
        return $this->state(fn (array $attributes) => [
            'urgency' => 'urgent',
        ]);
    }

    public function assigned(?User $practitioner = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'assigned',
            'assigned_to' => $practitioner?->id ?? User::factory(),
            'assigned_at' => now()->subHours(rand(1, 24)),
        ]);
    }

    public function completed(?User $practitioner = null): static
    {
        $assignedAt = now()->subDays(rand(1, 3));
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'assigned_to' => $practitioner?->id ?? User::factory(),
            'assigned_at' => $assignedAt,
            'completed_at' => (clone $assignedAt)->addHours(rand(2, 24)),
            'rating' => rand(4, 5),
            'feedback_note' => 'Practitioner provided excellent guidance and timely intervention.',
        ]);
    }
}
