<?php

namespace Database\Seeders;

use App\Models\District;
use App\Models\Division;
use App\Models\Union;
use App\Models\Upazila;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class BengaliGeocodeSeeder extends Seeder
{
    /**
     * Run the database seeds to ensure complete Bengali names across all administrative hierarchy.
     */
    public function run(): void
    {
        // 1. Divisions
        $divisionBnMap = [
            'Chattagram' => 'চট্টগ্রাম',
            'Chittagong' => 'চট্টগ্রাম',
            'Rajshahi' => 'রাজশাহী',
            'Khulna' => 'খুলনা',
            'Barisal' => 'বরিশাল',
            'Barishal' => 'বরিশাল',
            'Sylhet' => 'সিলেট',
            'Dhaka' => 'ঢাকা',
            'Rangpur' => 'রংপুর',
            'Mymensingh' => 'ময়মনসিংহ',
        ];

        foreach (Division::all() as $division) {
            if (empty($division->bn_name) && isset($divisionBnMap[$division->name])) {
                $division->update(['bn_name' => $divisionBnMap[$division->name]]);
            } elseif (empty($division->bn_name)) {
                Log::warning("Division {$division->id} ({$division->name}) is missing bn_name.");
            }
        }

        // 2. Districts
        $districtsWithoutBn = District::whereNull('bn_name')->orWhere('bn_name', '')->get();
        if ($districtsWithoutBn->count() > 0) {
            $this->command?->warn("Found {$districtsWithoutBn->count()} districts without bn_name. Cross-referencing package seeders...");
            // Cross-reference from package DistrictSeeder
            $pkgFile = base_path('vendor/devfaysal/laravel-bangladesh-geocode/src/Seeders/DistrictSeeder.php');
            if (file_exists($pkgFile)) {
                $content = file_get_contents($pkgFile);
                if (preg_match('/\$districts\s*=\s*(\[.*?\]);/s', $content, $matches)) {
                    $pkgDistricts = eval('return ' . $matches[1] . ';');
                    $map = [];
                    foreach ($pkgDistricts as $d) {
                        $map[strtolower(trim($d['name']))] = $d['bn_name'];
                        $map[(int) $d['id']] = $d['bn_name'];
                    }
                    foreach ($districtsWithoutBn as $dist) {
                        $key = strtolower(trim($dist->name));
                        if (isset($map[$key])) {
                            $dist->update(['bn_name' => $map[$key]]);
                        } elseif (isset($map[$dist->id])) {
                            $dist->update(['bn_name' => $map[$dist->id]]);
                        } else {
                            Log::warning("District #{$dist->id} ({$dist->name}) could not be matched for bn_name.");
                        }
                    }
                }
            }
        }

        // 3. Upazilas
        $upazilasWithoutBn = Upazila::whereNull('bn_name')->orWhere('bn_name', '')->get();
        if ($upazilasWithoutBn->count() > 0) {
            $this->command?->warn("Found {$upazilasWithoutBn->count()} upazilas without bn_name. Matching programmatically...");
            $pkgFile = base_path('vendor/devfaysal/laravel-bangladesh-geocode/src/Seeders/UpazilaSeeder.php');
            if (file_exists($pkgFile)) {
                $content = file_get_contents($pkgFile);
                if (preg_match('/\$upazilas\s*=\s*(\[.*?\]);/s', $content, $matches)) {
                    $pkgUpazilas = eval('return ' . $matches[1] . ';');
                    $map = [];
                    foreach ($pkgUpazilas as $u) {
                        $map[(int) $u['id']] = $u['bn_name'];
                        $map[$u['district_id'] . '_' . strtolower(trim($u['name']))] = $u['bn_name'];
                    }
                    foreach ($upazilasWithoutBn as $up) {
                        $compositeKey = $up->district_id . '_' . strtolower(trim($up->name));
                        if (isset($map[$compositeKey])) {
                            $up->update(['bn_name' => $map[$compositeKey]]);
                        } elseif (isset($map[$up->id])) {
                            $up->update(['bn_name' => $map[$up->id]]);
                        } else {
                            Log::warning("Upazila #{$up->id} ({$up->name}, District: {$up->district_id}) flagged for manual review.");
                        }
                    }
                }
            }
        }

        // 4. Unions
        $unionsWithoutBn = Union::whereNull('bn_name')->orWhere('bn_name', '')->get();
        if ($unionsWithoutBn->count() > 0) {
            $this->command?->warn("Found {$unionsWithoutBn->count()} unions without bn_name. Cross-referencing...");
            $pkgFile = base_path('vendor/devfaysal/laravel-bangladesh-geocode/src/Seeders/UnionSeeder.php');
            if (file_exists($pkgFile)) {
                $content = file_get_contents($pkgFile);
                if (preg_match('/\$unions\s*=\s*(\[.*?\]);/s', $content, $matches)) {
                    $pkgUnions = eval('return ' . $matches[1] . ';');
                    $map = [];
                    foreach ($pkgUnions as $un) {
                        $map[(int) $un['id']] = $un['bn_name'];
                        $map[$un['upazila_id'] . '_' . strtolower(trim($un['name']))] = $un['bn_name'];
                    }
                    foreach ($unionsWithoutBn as $u) {
                        $compositeKey = $u->upazila_id . '_' . strtolower(trim($u->name));
                        if (isset($map[$compositeKey])) {
                            $u->update(['bn_name' => $map[$compositeKey]]);
                        } elseif (isset($map[$u->id])) {
                            $u->update(['bn_name' => $map[$u->id]]);
                        } else {
                            Log::warning("Union #{$u->id} ({$u->name}, Upazila: {$u->upazila_id}) flagged for manual review.");
                        }
                    }
                }
            }
        }

        $divCount = Division::whereNotNull('bn_name')->count();
        $distCount = District::whereNotNull('bn_name')->count();
        $upCount = Upazila::whereNotNull('bn_name')->count();
        $unCount = Union::whereNotNull('bn_name')->count();

        $this->command?->info("Verified Bengali names: {$divCount}/" . Division::count() . " divisions, {$distCount}/" . District::count() . " districts, {$upCount}/" . Upazila::count() . " upazilas, {$unCount}/" . Union::count() . " unions.");
    }
}
