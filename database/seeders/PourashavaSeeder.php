<?php

namespace Database\Seeders;

use App\Models\Pourashava;
use App\Models\Upazila;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PourashavaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Extract curated Pourashava data from package's PourashavaSeeder dataset
        $sourceFile = base_path('vendor/devfaysal/laravel-bangladesh-geocode/src/Seeders/PourashavaSeeder.php');
        
        if (!file_exists($sourceFile)) {
            $this->command?->error("Package Pourashava source file not found at: {$sourceFile}");
            return;
        }

        $content = file_get_contents($sourceFile);
        if (!preg_match('/\$pourashavas\s*=\s*(\[.*?\]);/s', $content, $matches)) {
            $this->command?->error("Could not parse pourashavas array from source file.");
            return;
        }

        $sourcePourashavas = eval('return ' . $matches[1] . ';');
        $validUpazilaIds = Upazila::pluck('id')->flip()->toArray();

        $inserted = 0;
        $unmatched = [];
        $rows = [];
        $now = now();

        foreach ($sourcePourashavas as $item) {
            $upazilaId = (int) $item['upazila_id'];

            if (!isset($validUpazilaIds[$upazilaId])) {
                $unmatched[] = $item;
                Log::warning("Pourashava '{$item['name']}' could not be matched to valid upazila_id: {$upazilaId}");
                continue;
            }

            $rows[] = [
                'id' => (int) $item['id'],
                'upazila_id' => $upazilaId,
                'name' => trim($item['name']),
                'bn_name' => trim($item['bn_name'] ?? '') ?: null,
                'url' => trim($item['url'] ?? '') ?: null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $inserted++;
        }

        if (!empty($rows)) {
            // Upsert in chunks
            foreach (array_chunk($rows, 100) as $chunk) {
                Pourashava::upsert(
                    $chunk,
                    ['id'],
                    ['upazila_id', 'name', 'bn_name', 'url', 'updated_at']
                );
            }
        }

        if ($this->command) {
            $this->command->info("Seeded {$inserted} pourashavas successfully.");
            if (count($unmatched) > 0) {
                $this->command->warn("Flagged " . count($unmatched) . " pourashavas with unresolvable upazila parent.");
            }
        }
    }
}
