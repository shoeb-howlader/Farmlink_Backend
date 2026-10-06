<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WeatherEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_fetch_weather_by_district_and_union(): void
    {
        Http::fake([
            'api.open-meteo.com/*' => Http::response([
                'current' => [
                    'time' => '2026-10-06T12:00',
                    'temperature_2m' => 29.5,
                    'apparent_temperature' => 33.2,
                    'relative_humidity_2m' => 78,
                    'precipitation' => 0.0,
                    'surface_pressure' => 1011.0,
                    'wind_speed_10m' => 12.0,
                    'wind_direction_10m' => 90,
                    'is_day' => 1,
                    'weather_code' => 1,
                ],
                'daily' => [
                    'time' => ['2026-10-06', '2026-10-07'],
                    'weather_code' => [1, 2],
                    'temperature_2m_max' => [32.0, 31.5],
                    'temperature_2m_min' => [25.0, 24.5],
                    'precipitation_probability_max' => [10, 25],
                    'uv_index_max' => [7.5, 6.8],
                ],
                'hourly' => [
                    'time' => ['2026-10-06T12:00', '2026-10-06T13:00'],
                    'temperature_2m' => [29.5, 30.1],
                    'precipitation_probability' => [10, 15],
                    'weather_code' => [1, 1],
                ],
            ], 200),
        ]);

        $response = $this->getJson('/api/v1/weather?district=Satkhira&upazila=Debhata&union=Kulia');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.location.district', 'Satkhira')
            ->assertJsonPath('data.location.upazila', 'Debhata')
            ->assertJsonPath('data.location.union', 'Kulia')
            ->assertJsonPath('data.current.temperature', 29.5)
            ->assertJsonPath('data.advisory.do_risk', 'optimal')
            ->assertJsonStructure([
                'success',
                'data' => [
                    'location' => ['district', 'upazila', 'union', 'display_name', 'latitude', 'longitude'],
                    'current' => ['temperature', 'apparent_temperature', 'humidity', 'wind_speed', 'condition', 'icon'],
                    'advisory' => ['do_risk', 'do_advice', 'feeding_rate_pct', 'liming_alert', 'water_temp_estimate'],
                    'daily',
                ],
            ]);
    }

    public function test_authenticated_farmer_weather_defaults_to_registered_farm(): void
    {
        $farmer = User::factory()->create([
            'district' => 'Bagerhat',
        ]);

        $farm = Farm::factory()->create([
            'user_id' => $farmer->id,
            'farm_name' => 'Sundarbans Shrimp Hatchery',
            'district' => 'Bagerhat',
            'upazila' => 'Mongla',
            'union' => 'Chila',
            'gps_lat' => 22.4833,
            'gps_lng' => 89.6000,
        ]);

        Http::fake([
            'api.open-meteo.com/*' => Http::response([
                'current' => [
                    'time' => '2026-10-06T12:00',
                    'temperature_2m' => 28.0,
                    'apparent_temperature' => 31.0,
                    'relative_humidity_2m' => 80,
                    'precipitation' => 12.5, // triggers rain warning
                    'surface_pressure' => 1008.0,
                    'wind_speed_10m' => 15.0,
                    'wind_direction_10m' => 180,
                    'is_day' => 1,
                    'weather_code' => 61,
                ],
                'daily' => [
                    'time' => ['2026-10-06'],
                    'weather_code' => [61],
                    'temperature_2m_max' => [30.0],
                    'temperature_2m_min' => [24.0],
                    'precipitation_probability_max' => [90],
                    'uv_index_max' => [4.0],
                ],
                'hourly' => [],
            ], 200),
        ]);

        $response = $this->actingAs($farmer)->getJson('/api/v1/weather');

        $response->assertStatus(200)
            ->assertJsonPath('data.location.farm_id', $farm->id)
            ->assertJsonPath('data.location.district', 'Bagerhat')
            ->assertJsonPath('data.location.upazila', 'Mongla')
            ->assertJsonPath('data.location.union', 'Chila')
            ->assertJsonPath('data.advisory.do_risk', 'high')
            ->assertJsonPath('data.advisory.liming_alert', true);
    }

    public function test_force_refresh_bypasses_cached_weather(): void
    {
        Http::fake([
            'api.open-meteo.com/*' => Http::sequence()
                ->push(['current' => ['temperature_2m' => 28.4, 'time' => '2026-10-06T12:00', 'apparent_temperature' => 30.0, 'relative_humidity_2m' => 70, 'precipitation' => 0, 'surface_pressure' => 1010, 'wind_speed_10m' => 5, 'wind_direction_10m' => 0, 'is_day' => 1, 'weather_code' => 0], 'daily' => [], 'hourly' => []], 200)
                ->push(['current' => ['temperature_2m' => 31.5, 'time' => '2026-10-06T13:00', 'apparent_temperature' => 35.0, 'relative_humidity_2m' => 70, 'precipitation' => 0, 'surface_pressure' => 1010, 'wind_speed_10m' => 5, 'wind_direction_10m' => 0, 'is_day' => 1, 'weather_code' => 0], 'daily' => [], 'hourly' => []], 200),
        ]);

        // First call caches 28.4
        $first = $this->getJson('/api/v1/weather?district=Khulna');
        $first->assertStatus(200)->assertJsonPath('data.current.temperature', 28.4);

        // Regular second call returns cached 28.4 without hitting sequence second item
        $second = $this->getJson('/api/v1/weather?district=Khulna');
        $second->assertStatus(200)->assertJsonPath('data.current.temperature', 28.4);

        // Force refresh call bypasses cache and gets 31.5
        $refreshed = $this->getJson('/api/v1/weather?district=Khulna&refresh=1');
        $refreshed->assertStatus(200)->assertJsonPath('data.current.temperature', 31.5);
    }
}

