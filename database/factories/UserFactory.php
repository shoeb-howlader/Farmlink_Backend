<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->unique()->numerify('017########'),
            'district' => fake()->randomElement(['Satkhira', 'Khulna', 'Bagerhat', 'Cox\'s Bazar', 'Mymensingh']),
            'gender' => fake()->randomElement(['male', 'female', 'unspecified']),
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
            'status' => 'active',
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
            'phone_verified_at' => null,
            'status' => 'pending_verification',
        ]);
    }

    /**
     * Indicate that the model's phone number should be unverified.
     */
    public function phoneUnverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'phone_verified_at' => null,
            'status' => 'pending_verification',
            'phone_otp' => '123456',
            'phone_otp_expires_at' => now()->addMinutes(10),
            'phone_otp_sent_at' => now(),
        ]);
    }

    /**
     * Assign a Spatie role to the created user.
     */
    public function withRole(string $role): static
    {
        return $this->afterCreating(function (User $user) use ($role) {
            $user->assignRole($role);
        });
    }

    public function admin(): static
    {
        return $this->withRole('admin');
    }

    public function dataEntryOperator(): static
    {
        return $this->withRole('data_entry_operator');
    }

    public function deo(): static
    {
        return $this->dataEntryOperator();
    }

    public function veterinaryDoctor(): static
    {
        return $this->withRole('veterinary_doctor');
    }

    public function vet(): static
    {
        return $this->veterinaryDoctor();
    }

    public function consultant(): static
    {
        return $this->withRole('consultant');
    }

    public function farmer(): static
    {
        return $this->withRole('farmer');
    }
}

