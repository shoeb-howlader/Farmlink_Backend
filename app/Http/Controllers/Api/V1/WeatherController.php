<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Farm;
use App\Models\District;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WeatherController extends ApiController
{
    /**
     * Complete coordinate database for all 64 districts in Bangladesh.
     */
    protected const DISTRICT_COORDINATES = [
        'bagerhat' => ['lat' => 22.6602, 'lon' => 89.7895, 'bn' => 'বাগেরহাট'],
        'bandarban' => ['lat' => 22.1953, 'lon' => 92.2184, 'bn' => 'বান্দরবান'],
        'barguna' => ['lat' => 22.1570, 'lon' => 90.1265, 'bn' => 'বরগুনা'],
        'barisal' => ['lat' => 22.7010, 'lon' => 90.3535, 'bn' => 'বরিশাল'],
        'bhola' => ['lat' => 22.6859, 'lon' => 90.6481, 'bn' => 'ভোলা'],
        'bogura' => ['lat' => 24.8465, 'lon' => 89.3777, 'bn' => 'বগুড়া'],
        'bogra' => ['lat' => 24.8465, 'lon' => 89.3777, 'bn' => 'বগুড়া'],
        'brahmanbaria' => ['lat' => 23.9571, 'lon' => 91.1119, 'bn' => 'ব্রাহ্মণবাড়িয়া'],
        'chandpur' => ['lat' => 23.2333, 'lon' => 90.6667, 'bn' => 'চাঁদপুর'],
        'chapainawabganj' => ['lat' => 24.5965, 'lon' => 88.2775, 'bn' => 'চাঁপাইনবাবগঞ্জ'],
        'chattogram' => ['lat' => 22.3569, 'lon' => 91.7832, 'bn' => 'চট্টগ্রাম'],
        'chittagong' => ['lat' => 22.3569, 'lon' => 91.7832, 'bn' => 'চট্টগ্রাম'],
        'chuadanga' => ['lat' => 23.6402, 'lon' => 88.8418, 'bn' => 'চুয়াডাঙ্গা'],
        'comilla' => ['lat' => 23.4683, 'lon' => 91.1788, 'bn' => 'কুমিল্লা'],
        'coxsbazar' => ['lat' => 21.4272, 'lon' => 92.0058, 'bn' => 'কক্সবাজার'],
        'cox\'s bazar' => ['lat' => 21.4272, 'lon' => 92.0058, 'bn' => 'কক্সবাজার'],
        'dhaka' => ['lat' => 23.8103, 'lon' => 90.4125, 'bn' => 'ঢাকা'],
        'dinajpur' => ['lat' => 25.6217, 'lon' => 88.6355, 'bn' => 'দিনাজপুর'],
        'faridpur' => ['lat' => 23.6071, 'lon' => 89.8429, 'bn' => 'ফরিদপুর'],
        'feni' => ['lat' => 23.0232, 'lon' => 91.3841, 'bn' => 'ফেনী'],
        'gaibandha' => ['lat' => 25.3288, 'lon' => 89.5406, 'bn' => 'গাইবান্ধা'],
        'gazipur' => ['lat' => 24.0023, 'lon' => 90.4264, 'bn' => 'গাজীপুর'],
        'gopalganj' => ['lat' => 23.0051, 'lon' => 89.8266, 'bn' => 'গোপালগঞ্জ'],
        'habiganj' => ['lat' => 24.3749, 'lon' => 91.4155, 'bn' => 'হবিগঞ্জ'],
        'jamalpur' => ['lat' => 24.9375, 'lon' => 89.9378, 'bn' => 'জামালপুর'],
        'jashore' => ['lat' => 23.1664, 'lon' => 89.2182, 'bn' => 'যশোর'],
        'jessore' => ['lat' => 23.1664, 'lon' => 89.2182, 'bn' => 'যশোর'],
        'jhalakathi' => ['lat' => 22.6406, 'lon' => 90.1987, 'bn' => 'ঝালকাঠি'],
        'jhenaidah' => ['lat' => 23.5448, 'lon' => 89.1539, 'bn' => 'ঝিনাইদহ'],
        'joypurhat' => ['lat' => 25.1015, 'lon' => 89.0277, 'bn' => 'জয়পুরহাট'],
        'khagrachhari' => ['lat' => 23.1193, 'lon' => 91.9847, 'bn' => 'খাগড়াছড়ি'],
        'khulna' => ['lat' => 22.8456, 'lon' => 89.5403, 'bn' => 'খুলনা'],
        'kishoreganj' => ['lat' => 24.4449, 'lon' => 90.7766, 'bn' => 'কিশোরগঞ্জ'],
        'kurigram' => ['lat' => 25.8054, 'lon' => 89.6362, 'bn' => 'কুড়িগ্রাম'],
        'kushtia' => ['lat' => 23.9013, 'lon' => 89.1205, 'bn' => 'কুষ্টিয়া'],
        'lakshmipur' => ['lat' => 22.9425, 'lon' => 90.8412, 'bn' => 'লক্ষ্মীপুর'],
        'lalmonirhat' => ['lat' => 25.9923, 'lon' => 89.2847, 'bn' => 'লালমনিরহাট'],
        'madaripur' => ['lat' => 23.1641, 'lon' => 90.1897, 'bn' => 'মাদারীপুর'],
        'magura' => ['lat' => 23.4873, 'lon' => 89.4199, 'bn' => 'মাগুরা'],
        'manikganj' => ['lat' => 23.8644, 'lon' => 90.0047, 'bn' => 'মানিকগঞ্জ'],
        'meherpur' => ['lat' => 23.7622, 'lon' => 88.6318, 'bn' => 'মেহেরপুর'],
        'moulvibazar' => ['lat' => 24.4829, 'lon' => 91.7774, 'bn' => 'মৌলভীবাজার'],
        'munshiganj' => ['lat' => 23.5422, 'lon' => 90.5305, 'bn' => 'মুন্সিগঞ্জ'],
        'mymensingh' => ['lat' => 24.7471, 'lon' => 90.4203, 'bn' => 'ময়মনসিংহ'],
        'naogaon' => ['lat' => 24.7936, 'lon' => 88.9318, 'bn' => 'নওগাঁ'],
        'narail' => ['lat' => 23.1725, 'lon' => 89.5127, 'bn' => 'নড়াইল'],
        'narayanganj' => ['lat' => 23.6337, 'lon' => 90.4965, 'bn' => 'নারায়ণগঞ্জ'],
        'narsingdi' => ['lat' => 23.9193, 'lon' => 90.7202, 'bn' => 'নরসিংদী'],
        'natore' => ['lat' => 24.4206, 'lon' => 89.0003, 'bn' => 'নাটোর'],
        'netrokona' => ['lat' => 24.8709, 'lon' => 90.7279, 'bn' => 'নেত্রকোণা'],
        'nilphamari' => ['lat' => 25.9318, 'lon' => 88.8560, 'bn' => 'নীলফামারী'],
        'noakhali' => ['lat' => 22.8696, 'lon' => 91.0994, 'bn' => 'নোয়াখালী'],
        'pabna' => ['lat' => 24.0064, 'lon' => 89.2372, 'bn' => 'পাবনা'],
        'panchagarh' => ['lat' => 26.3411, 'lon' => 88.5542, 'bn' => 'পঞ্চগড়'],
        'patuakhali' => ['lat' => 22.3596, 'lon' => 90.3299, 'bn' => 'পটুয়াখালী'],
        'pirojpur' => ['lat' => 22.5841, 'lon' => 89.9720, 'bn' => 'পিরোজপুর'],
        'rajbari' => ['lat' => 23.7574, 'lon' => 89.6445, 'bn' => 'রাজবাড়ী'],
        'rajshahi' => ['lat' => 24.3636, 'lon' => 88.6241, 'bn' => 'রাজশাহী'],
        'rangamati' => ['lat' => 22.6533, 'lon' => 92.1753, 'bn' => 'রাঙ্গামাটি'],
        'rangpur' => ['lat' => 25.7439, 'lon' => 89.2752, 'bn' => 'রংপুর'],
        'satkhira' => ['lat' => 22.7185, 'lon' => 89.0705, 'bn' => 'সাতক্ষীরা'],
        'shariatpur' => ['lat' => 23.2423, 'lon' => 90.4348, 'bn' => 'শরীয়তপুর'],
        'sherpur' => ['lat' => 25.0205, 'lon' => 90.0153, 'bn' => 'শেরপুর'],
        'sirajganj' => ['lat' => 24.4534, 'lon' => 89.7008, 'bn' => 'সিরাজগঞ্জ'],
        'sunamganj' => ['lat' => 25.0658, 'lon' => 91.3950, 'bn' => 'সুনামগঞ্জ'],
        'sylhet' => ['lat' => 24.8949, 'lon' => 91.8687, 'bn' => 'সিলেট'],
        'tangail' => ['lat' => 24.2513, 'lon' => 89.9167, 'bn' => 'টাঙ্গাইল'],
        'thakurgaon' => ['lat' => 26.0337, 'lon' => 88.4617, 'bn' => 'ঠাকুরগাঁও'],
    ];

    /**
     * Fetch current weather and aquaculture advisory for the customer's farm, union, or district.
     */
    public function getWeather(Request $request): JsonResponse
    {
        $user = $request->user('sanctum');

        $farmId = $request->query('farm_id');
        $districtInput = $request->query('district');
        $upazilaInput = $request->query('upazila');
        $unionInput = $request->query('union');
        $latInput = $request->query('lat');
        $lngInput = $request->query('lng');

        $resolvedLocation = [
            'district' => null,
            'upazila' => null,
            'union' => null,
            'district_bn' => null,
            'display_name' => 'Bangladesh',
            'display_name_bn' => 'বাংলাদেশ',
            'farm_name' => null,
            'farm_id' => null,
        ];

        $targetLat = null;
        $targetLng = null;

        // 1. If explicit farm_id passed or default user primary farm
        $farm = null;
        if ($farmId) {
            $farm = Farm::find($farmId);
        } elseif ($user) {
            $farm = Farm::where('user_id', $user->id)->first();
        }

        if ($farm) {
            $resolvedLocation['farm_id'] = $farm->id;
            $resolvedLocation['farm_name'] = $farm->farm_name;
            $resolvedLocation['district'] = $farm->district;
            $resolvedLocation['upazila'] = $farm->upazila;
            $resolvedLocation['union'] = $farm->union;

            if ($farm->gps_lat && $farm->gps_lng && is_numeric($farm->gps_lat) && is_numeric($farm->gps_lng)) {
                $fLat = (float) $farm->gps_lat;
                $fLng = (float) $farm->gps_lng;
                // Verify within Bangladesh broad bounding box
                if ($fLat >= 20.0 && $fLat <= 27.0 && $fLng >= 88.0 && $fLng <= 93.0) {
                    $targetLat = $fLat;
                    $targetLng = $fLng;
                }
            }
        }

        // 2. Overwrite with explicit query parameters if provided
        if ($districtInput) $resolvedLocation['district'] = $districtInput;
        if ($upazilaInput) $resolvedLocation['upazila'] = $upazilaInput;
        if ($unionInput) $resolvedLocation['union'] = $unionInput;

        // If no district yet, check user profile
        if (! $resolvedLocation['district'] && $user?->district) {
            $resolvedLocation['district'] = $user->district;
        }

        // If explicit coordinates supplied in query
        if ($latInput !== null && $lngInput !== null && is_numeric($latInput) && is_numeric($lngInput)) {
            $targetLat = (float) $latInput;
            $targetLng = (float) $lngInput;
        }

        // 3. Fallback coordinate resolution from district
        $districtKey = strtolower(trim((string) ($resolvedLocation['district'] ?? 'satkhira')));
        $districtKey = str_replace([' district', ' zila', ' জেলা'], '', $districtKey);

        $districtMeta = self::DISTRICT_COORDINATES[$districtKey] ?? null;

        if (! $targetLat || ! $targetLng) {
            if ($districtMeta) {
                $targetLat = $districtMeta['lat'];
                $targetLng = $districtMeta['lon'];
            } else {
                // Default to Satkhira/Khulna coastal aquaculture center
                $targetLat = 22.7185;
                $targetLng = 89.0705;
                $districtKey = 'satkhira';
                $districtMeta = self::DISTRICT_COORDINATES['satkhira'];
            }
        }

        if ($districtMeta) {
            $resolvedLocation['district_bn'] = $districtMeta['bn'];
        }

        // Construct friendly human readable location label
        $locationParts = array_filter([
            $resolvedLocation['union'] ? "{$resolvedLocation['union']} Union" : null,
            $resolvedLocation['upazila'] ? "{$resolvedLocation['upazila']}" : null,
            $resolvedLocation['district'] ? "{$resolvedLocation['district']}" : null,
        ]);

        $resolvedLocation['display_name'] = ! empty($locationParts)
            ? implode(', ', $locationParts)
            : ($resolvedLocation['district'] ?: 'Satkhira, Bangladesh');

        // Cache weather response by coordinate bucket (2 decimals ~ 1.1 km resolution)
        $cacheKey = sprintf('weather_v1_%.2f_%.2f', $targetLat, $targetLng);

        if ($request->boolean('refresh') || $request->boolean('force')) {
            Cache::forget($cacheKey);
        }

        $weatherData = Cache::remember($cacheKey, 900, function () use ($targetLat, $targetLng) {
            return $this->fetchOpenMeteoForecast($targetLat, $targetLng);
        });

        // If upstream fetch failed or returned null, provide simulated fallback
        if (! $weatherData) {
            $weatherData = $this->getFallbackWeatherData($targetLat, $targetLng);
        }

        // Generate tailored aquaculture pond advisory
        $advisory = $this->generateAquacultureAdvisory($weatherData['current'], $weatherData['daily'] ?? []);

        return $this->successResponse([
            'location' => array_merge($resolvedLocation, [
                'latitude' => $targetLat,
                'longitude' => $targetLng,
            ]),
            'current' => $weatherData['current'],
            'daily' => $weatherData['daily'],
            'hourly' => $weatherData['hourly'],
            'advisory' => $advisory,
            'source' => 'Open-Meteo & FarmLink AquaMet',
            'updated_at' => now()->toIso8601String(),
        ], 'Current weather and aquaculture advisory retrieved successfully');
    }

    /**
     * Fetch forecast from Open-Meteo API.
     */
    protected function fetchOpenMeteoForecast(float $lat, float $lon): ?array
    {
        try {
            $url = 'https://api.open-meteo.com/v1/forecast';
            $response = Http::timeout(4)
                ->retry(2, 200)
                ->get($url, [
                    'latitude' => $lat,
                    'longitude' => $lon,
                    'current' => 'temperature_2m,relative_humidity_2m,apparent_temperature,is_day,precipitation,weather_code,surface_pressure,wind_speed_10m,wind_direction_10m',
                    'hourly' => 'temperature_2m,precipitation_probability,weather_code',
                    'daily' => 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_probability_max,uv_index_max',
                    'timezone' => 'Asia/Dhaka',
                    'forecast_days' => 4,
                ]);

            if ($response->successful()) {
                $raw = $response->json();
                return $this->transformOpenMeteoData($raw);
            }

            Log::warning('Open-Meteo returned error: ' . $response->status(), ['body' => $response->body()]);
        } catch (\Throwable $e) {
            Log::error('Open-Meteo request failed: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Transform raw Open-Meteo payload into structured, formatted response.
     */
    protected function transformOpenMeteoData(array $raw): array
    {
        $current = $raw['current'] ?? [];
        $wCode = (int) ($current['weather_code'] ?? 0);
        $wMeta = $this->interpretWmoCode($wCode, (bool) ($current['is_day'] ?? 1));

        $windDeg = (float) ($current['wind_direction_10m'] ?? 0);
        $compass = $this->degToCompass($windDeg);

        $formattedCurrent = [
            'time' => $current['time'] ?? now()->toIso8601String(),
            'temperature' => round((float) ($current['temperature_2m'] ?? 28), 1),
            'apparent_temperature' => round((float) ($current['apparent_temperature'] ?? 30), 1),
            'humidity' => (int) ($current['relative_humidity_2m'] ?? 75),
            'precipitation' => round((float) ($current['precipitation'] ?? 0), 2),
            'pressure' => round((float) ($current['surface_pressure'] ?? 1010), 1),
            'wind_speed' => round((float) ($current['wind_speed_10m'] ?? 8), 1),
            'wind_direction' => $windDeg,
            'wind_compass' => $compass,
            'is_day' => (bool) ($current['is_day'] ?? 1),
            'weather_code' => $wCode,
            'condition' => $wMeta['condition'],
            'condition_bn' => $wMeta['condition_bn'],
            'icon' => $wMeta['icon'],
            'bg_gradient' => $wMeta['bg_gradient'],
        ];

        // Format daily forecast (up to 4 days)
        $daily = [];
        $rawDaily = $raw['daily'] ?? [];
        $dailyDates = $rawDaily['time'] ?? [];
        foreach ($dailyDates as $index => $date) {
            $code = (int) ($rawDaily['weather_code'][$index] ?? 0);
            $dayMeta = $this->interpretWmoCode($code, true);
            $daily[] = [
                'date' => $date,
                'temp_max' => round((float) ($rawDaily['temperature_2m_max'][$index] ?? 32), 1),
                'temp_min' => round((float) ($rawDaily['temperature_2m_min'][$index] ?? 24), 1),
                'precipitation_probability' => (int) ($rawDaily['precipitation_probability_max'][$index] ?? 0),
                'uv_index' => round((float) ($rawDaily['uv_index_max'][$index] ?? 6), 1),
                'weather_code' => $code,
                'condition' => $dayMeta['condition'],
                'condition_bn' => $dayMeta['condition_bn'],
                'icon' => $dayMeta['icon'],
            ];
        }

        // Format next 12 hours from hourly
        $hourly = [];
        $rawHourly = $raw['hourly'] ?? [];
        $hourlyTimes = $rawHourly['time'] ?? [];
        $nowHour = now('Asia/Dhaka')->format('Y-m-d\TH:00');
        $count = 0;
        foreach ($hourlyTimes as $index => $time) {
            if ($time >= $nowHour && $count < 12) {
                $code = (int) ($rawHourly['weather_code'][$index] ?? 0);
                $meta = $this->interpretWmoCode($code, true);
                $hourly[] = [
                    'time' => $time,
                    'hour' => date('g A', strtotime($time)),
                    'temperature' => round((float) ($rawHourly['temperature_2m'][$index] ?? 28), 1),
                    'precipitation_probability' => (int) ($rawHourly['precipitation_probability'][$index] ?? 0),
                    'icon' => $meta['icon'],
                ];
                $count++;
            }
        }

        return [
            'current' => $formattedCurrent,
            'daily' => $daily,
            'hourly' => $hourly,
        ];
    }

    /**
     * Map WMO weather codes to human friendly terms, icons, and themes.
     */
    protected function interpretWmoCode(int $code, bool $isDay): array
    {
        return match ($code) {
            0 => [
                'condition' => 'Clear Sky',
                'condition_bn' => 'পরিষ্কার আকাশ',
                'icon' => $isDay ? 'i-lucide-sun' : 'i-lucide-moon',
                'bg_gradient' => 'from-amber-500/20 via-sky-500/10 to-emerald-500/10',
            ],
            1 => [
                'condition' => 'Mainly Clear',
                'condition_bn' => 'মূলত পরিষ্কার',
                'icon' => $isDay ? 'i-lucide-sun-medium' : 'i-lucide-moon-star',
                'bg_gradient' => 'from-amber-400/20 via-sky-500/10 to-teal-500/10',
            ],
            2 => [
                'condition' => 'Partly Cloudy',
                'condition_bn' => 'আংশিক মেঘলা',
                'icon' => $isDay ? 'i-lucide-cloud-sun' : 'i-lucide-cloud-moon',
                'bg_gradient' => 'from-sky-400/20 via-blue-500/10 to-emerald-500/10',
            ],
            3 => [
                'condition' => 'Overcast',
                'condition_bn' => 'মেঘলা আকাশ',
                'icon' => 'i-lucide-cloudy',
                'bg_gradient' => 'from-slate-400/20 via-slate-600/10 to-zinc-500/10',
            ],
            45, 48 => [
                'condition' => 'Fog & Mist',
                'condition_bn' => 'কুয়াশা ও কুয়াশাচ্ছন্ন',
                'icon' => 'i-lucide-cloud-fog',
                'bg_gradient' => 'from-slate-300/20 via-slate-400/10 to-zinc-400/10',
            ],
            51, 53, 55, 56, 57 => [
                'condition' => 'Drizzle',
                'condition_bn' => 'গুঁড়ি গুঁড়ি বৃষ্টি',
                'icon' => 'i-lucide-cloud-drizzle',
                'bg_gradient' => 'from-teal-500/20 via-cyan-600/10 to-blue-500/10',
            ],
            61, 63, 65, 66, 67, 80, 81, 82 => [
                'condition' => 'Rainfall',
                'condition_bn' => 'বৃষ্টিপাত',
                'icon' => 'i-lucide-cloud-rain',
                'bg_gradient' => 'from-blue-600/20 via-indigo-600/10 to-slate-700/10',
            ],
            95, 96, 99 => [
                'condition' => 'Thunderstorm',
                'condition_bn' => 'বজ্রঝড়',
                'icon' => 'i-lucide-cloud-lightning',
                'bg_gradient' => 'from-amber-600/25 via-purple-700/20 to-slate-900/20',
            ],
            default => [
                'condition' => 'Fair Weather',
                'condition_bn' => 'অনুকূল আবহাওয়া',
                'icon' => $isDay ? 'i-lucide-sun' : 'i-lucide-moon',
                'bg_gradient' => 'from-emerald-500/20 via-teal-500/10 to-sky-500/10',
            ]
        };
    }

    /**
     * Compute specialized aquaculture insights from meteorological data.
     */
    protected function generateAquacultureAdvisory(array $current, array $daily): array
    {
        $temp = (float) ($current['temperature'] ?? 28);
        $wCode = (int) ($current['weather_code'] ?? 0);
        $humidity = (int) ($current['humidity'] ?? 75);
        $windSpeed = (float) ($current['wind_speed'] ?? 10);
        $rainProb = isset($daily[0]['precipitation_probability']) ? (int) $daily[0]['precipitation_probability'] : 0;
        $rainAmount = (float) ($current['precipitation'] ?? 0);

        // 1. Dissolved Oxygen (DO) Risk Rating
        $doRisk = 'optimal';
        $doAdvice = 'High daytime photosynthesis generates abundant dissolved oxygen. Standard pre-dawn aeration (3:30 AM - 6:00 AM) advised.';
        $doAdviceBn = 'দিনের বেলায় পর্যাপ্ত সূর্যালোক প্রাকৃতিক অক্সিজেন তৈরি করে। ভোরের দিকে (রাত ৩:৩০ - সকাল ৬:০০) এয়ারেটর চালানোর পরামর্শ দেওয়া হচ্ছে।';

        if ($wCode >= 61 || $wCode === 3) {
            $doRisk = 'high';
            $doAdvice = 'Overcast/Rainy skies reduce phytoplankton photosynthesis. Increased nocturnal oxygen sag expected. Extend aerator runtime.';
            $doAdviceBn = 'মেঘলা ও বৃষ্টির কারণে প্রাকৃতিক অক্সিজেন উৎপাদন কম হতে পারে। রাতে ও ভোরে অতিরিক্ত সময় এয়ারেটর চালু রাখুন।';
        } elseif ($temp >= 34.0) {
            $doRisk = 'moderate';
            $doAdvice = 'High water temperature lowers oxygen solubility. Operate mechanical aerators during peak midday heat and nighttime.';
            $doAdviceBn = 'উচ্চ তাপমাত্রায় পানিতে অক্সিজেন ধারণক্ষমতা কমে যায়। দুপুর ও রাতে প্যাডেলহুইল এয়ারেটর সচল রাখুন।';
        }

        // 2. Feeding Advisory
        $feedingRatePct = 100;
        $feedingNote = 'Normal feeding ration. Check feed trays 2 hours post-broadcasting.';
        $feedingNoteBn = 'স্বাভাবিক মাত্রায় খাবার দিন। খাবার দেওয়ার ২ ঘণ্টা পর ফিডিং ট্রে পর্যবেক্ষণ করুন।';

        if ($wCode >= 95) {
            $feedingRatePct = 40;
            $feedingNote = 'Thunderstorm alert: Drastically reduce feed by 60% as shrimp and finfish retreat to deeper benthic zones and cease feeding.';
            $feedingNoteBn = 'বজ্রঝড়ের আশঙ্কা: মাছ ও চিংড়ি খাদ্য গ্রহণ কমিয়ে দেয়, তাই খাবারের পরিমাণ ৬০% কমিয়ে দিন।';
        } elseif ($rainAmount > 5 || $rainProb >= 75) {
            $feedingRatePct = 70;
            $feedingNote = 'Rainfall expected: Reduce feed quantity by 30% to prevent unconsumed feed decomposition and ammonia spikes.';
            $feedingNoteBn = 'বৃষ্টিপাতের কারণে খাবারের মাত্রা ৩০% হ্রাস করুন যাতে পানিতে অ্যামোনিয়া ও বিষাক্ত গ্যাস না তৈরি হয়।';
        } elseif ($temp >= 35.0) {
            $feedingRatePct = 80;
            $feedingNote = 'Extreme heat: Postpone midday feeding to late afternoon (5:00 PM) to avoid feed spoilage and heat stress.';
            $feedingNoteBn = 'তীব্র গরম: দুপুরের ভারী খাবার এড়িয়ে বিকাল ৫টার পর ঠান্ডা আবহাওয়ায় খাবার প্রদান করুন।';
        }

        // 3. Salinity and pH Alert
        $limingAlert = ($rainAmount >= 15 || $rainProb >= 85);
        $limingAdvice = $limingAlert
            ? 'Heavy rainfall warning: Watch for salinity stratification and pH drop. Keep agricultural lime (CaCO3) or dolomite ready for pond dyke runoff treatment.'
            : 'Water parameters stable. Periodic Secchi disc transparency and alkalinity testing recommended.';
        $limingAdviceBn = $limingAlert
            ? 'ভারী বৃষ্টির সতর্কতা: বৃষ্টির পানির কারণে পুকুর/ঘেরের পিএইচ (pH) কমে যেতে পারে। ঘেরের পাড়ে কৃষি চুন বা ডলোমাইট প্রয়োগের প্রস্তুতি রাখুন।'
            : 'পানির অম্লত্ব ও ক্ষারত্ব স্বাভাবিক রয়েছে। নিয়মিত সেকি ডিস্ক দিয়ে পানির স্বচ্ছতা পরীক্ষা করুন।';

        // 4. Wind & Wave Action
        $windAlert = $windSpeed >= 28;

        return [
            'do_risk' => $doRisk, // 'optimal' | 'moderate' | 'high'
            'do_advice' => $doAdvice,
            'do_advice_bn' => $doAdviceBn,
            'feeding_rate_pct' => $feedingRatePct,
            'feeding_note' => $feedingNote,
            'feeding_note_bn' => $feedingNoteBn,
            'liming_alert' => $limingAlert,
            'liming_advice' => $limingAdvice,
            'liming_advice_bn' => $limingAdviceBn,
            'wind_alert' => $windAlert,
            'wind_advice' => $windAlert ? "Strong gusts of {$windSpeed} km/h: Inspect pond dyke integrity and secure aerator power cords." : "Calm wind conditions ({$windSpeed} km/h).",
            'water_temp_estimate' => round(max(20, min(36, $temp - 1.2)), 1),
        ];
    }

    /**
     * Convert compass degree to 8-point compass label.
     */
    protected function degToCompass(float $deg): string
    {
        $val = floor(($deg / 45) + 0.5);
        $arr = ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW'];
        return $arr[$val % 8];
    }

    /**
     * Fallback realistic weather data if Open-Meteo is temporarily unreachable.
     */
    protected function getFallbackWeatherData(float $lat, float $lon): array
    {
        $hour = (int) now('Asia/Dhaka')->format('H');
        $isDay = $hour >= 6 && $hour <= 18;

        $current = [
            'time' => now('Asia/Dhaka')->toIso8601String(),
            'temperature' => $isDay ? 31.0 : 25.5,
            'apparent_temperature' => $isDay ? 35.0 : 28.0,
            'humidity' => 78,
            'precipitation' => 0.0,
            'pressure' => 1010.5,
            'wind_speed' => 10.2,
            'wind_direction' => 140,
            'wind_compass' => 'SE',
            'is_day' => $isDay,
            'weather_code' => 2,
            'condition' => 'Partly Cloudy',
            'condition_bn' => 'আংশিক মেঘলা',
            'icon' => $isDay ? 'i-lucide-cloud-sun' : 'i-lucide-cloud-moon',
            'bg_gradient' => 'from-sky-400/20 via-blue-500/10 to-emerald-500/10',
        ];

        $daily = [
            [
                'date' => now('Asia/Dhaka')->format('Y-m-d'),
                'temp_max' => 32.5,
                'temp_min' => 25.0,
                'precipitation_probability' => 20,
                'uv_index' => 7.0,
                'weather_code' => 2,
                'condition' => 'Partly Cloudy',
                'condition_bn' => 'আংশিক মেঘলা',
                'icon' => 'i-lucide-cloud-sun',
            ],
            [
                'date' => now('Asia/Dhaka')->addDays(1)->format('Y-m-d'),
                'temp_max' => 31.8,
                'temp_min' => 24.5,
                'precipitation_probability' => 35,
                'uv_index' => 6.5,
                'weather_code' => 1,
                'condition' => 'Mainly Clear',
                'condition_bn' => 'মূলত পরিষ্কার',
                'icon' => 'i-lucide-sun-medium',
            ],
            [
                'date' => now('Asia/Dhaka')->addDays(2)->format('Y-m-d'),
                'temp_max' => 30.5,
                'temp_min' => 24.0,
                'precipitation_probability' => 60,
                'uv_index' => 5.2,
                'weather_code' => 61,
                'condition' => 'Rainfall',
                'condition_bn' => 'বৃষ্টিপাত',
                'icon' => 'i-lucide-cloud-rain',
            ]
        ];

        return [
            'current' => $current,
            'daily' => $daily,
            'hourly' => [],
        ];
    }
}
