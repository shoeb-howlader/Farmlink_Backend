<?php

namespace Database\Factories;

use App\Models\Farm;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Farm>
 */
class FarmFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cultureTypes = ['Bagda', 'Golda', 'Tilapia', 'Pangas', 'Carp'];
        $farmingSystems = ['Extensive', 'Improved Extensive', 'Semi-intensive', 'Intensive'];
        $farmTypes = ['Own', 'Leased', 'Family', 'Partnership'];
        $districts = ['Satkhira', 'Khulna', 'Bagerhat', 'Cox\'s Bazar', 'Mymensingh'];
        $upazilas = ['Shyamnagar', 'Assasuni', 'Debhata', 'Kaliganj', 'Paikgachha', 'Koyra'];
        $waterSources = ['River', 'Canal', 'Deep Tubewell', 'Pond/Groundwater'];

        $villages = [
            'Burigoalini', 'Munshiganj', 'Padmapukur', 'Gabura', 'Atulia', 'Nurnagar', 'Kashimari',
            'Koikhali', 'Dumuria', 'Batiaghata', 'Chitalmari', 'Fakirhat', 'Rampal', 'Mongla',
            'Sarankhola', 'Moheshkhali', 'Teknaf', 'Ukhiya', 'Chokoria', 'Trishal', 'Muktagachha',
        ];
        $unions = [
            'Munshiganj Union', 'Burigoalini Union', 'Padmapukur Union', 'Gabura Union', 'Atulia Union',
            'Dumuria Sadar Union', 'Batiaghata Union', 'Chitalmari Sadar Union', 'Fakirhat Union', 'Rampal Union',
        ];

        $district = fake()->randomElement($districts);
        $upazila = fake()->randomElement($upazilas);
        $village = fake()->randomElement($villages);
        $union = fake()->randomElement($unions);

        $addressFormats = [
            "Village: {$village}, Union: {$union}, Upazila: {$upazila}",
            'Ward '.fake()->numberBetween(1, 9).", Near {$village} Bazar, {$upazila}",
            "Station Road, {$village} Para, {$upazila}",
            'Pond Complex #'.fake()->numberBetween(1, 20).", {$village}, {$upazila}",
            "Embankment Road, {$village}, Post: {$village}, {$upazila}",
        ];

        return [
            'user_id' => User::factory(),
            'farm_name' => fake()->company().' Aqua Farm',
            'farm_type' => fake()->randomElement($farmTypes),
            'total_area' => fake()->randomFloat(2, 1, 50),
            'pond_count' => fake()->numberBetween(1, 10),
            'cultivation_area' => fake()->randomFloat(2, 1, 40),
            'district' => $district,
            'upazila' => $upazila,
            'union' => $union,
            'village' => $village,
            'farm_address' => fake()->randomElement($addressFormats),
            'gps_lat' => fake()->latitude(21.5, 23.5),
            'gps_lng' => fake()->longitude(89.0, 92.0),
            'aquaculture_experience_years' => fake()->numberBetween(1, 30),
            'previous_farming_experience' => fake()->randomElement(['Shrimp', 'Golda', 'Fish', 'Mixed']),
            'main_culture_type' => fake()->randomElement($cultureTypes),
            'farming_system' => fake()->randomElement($farmingSystems),
            'main_water_source' => fake()->randomElement($waterSources),
            'water_exchange_facility' => fake()->randomElement(['Yes', 'No', 'Partial']),
            'water_source_distance' => fake()->numberBetween(10, 500).'m',
            'available_facilities' => fake()->randomElements([
                'Electric', 'Generator', 'Solar', 'Pump', 'Aerator', 'Feeding Equip.', 'Storage', 'Net/Harvest', 'Fencing',
            ], fake()->numberBetween(2, 6)),
            'aerator_count' => fake()->numberBetween(1, 4),
            'aerator_hp' => fake()->randomElement(['1 HP', '2 HP', '3 HP']),
            'aerator_hours_per_day' => fake()->randomFloat(1, 4, 14),
            'farm_manager' => fake()->randomElement(['Self', 'Family', 'Hired Manager']),
            'technical_support_used' => fake()->randomElement(['Govt Extension', 'Feed Company Officer', 'Local Vet', 'None']),
            'main_advice_source' => fake()->randomElement(['Feed Dealer', 'Neighbor Farmers', 'Consultant', 'Self-experience']),
        ];
    }
}
