<?php

namespace App\Console\Commands;

use App\Models\District;
use App\Models\Division;
use App\Models\Farm;
use App\Models\Pourashava;
use App\Models\Union;
use App\Models\Upazila;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MigrateFarmLocationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'farmlink:migrate-farm-locations {--dry-run : Run without updating database records}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate free-text farm location fields to canonical foreign keys with confident matching and flagging';

    /**
     * Common district aliases and transliterations.
     */
    protected array $districtAliases = [
        "cox's bazar" => 'coxsbazar',
        'chittagong' => 'chattogram',
        'cumilla' => 'comilla',
        'barishal' => 'barisal',
        'jashore' => 'jessore',
        'bogura' => 'bogra',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        if ($isDryRun) {
            $this->warn('Running in DRY-RUN mode. No database records will be modified.');
        }

        $districts = District::with('division')->get();
        $farms = Farm::all();

        $this->info("Analyzing location fields for {$farms->count()} farms...");

        $stats = [
            'total' => $farms->count(),
            'fully_matched' => 0,
            'partially_matched' => 0,
            'unmatched' => 0,
        ];

        $flagged = [];

        foreach ($farms as $farm) {
            $dName = strtolower(trim($farm->district ?? ''));
            if (isset($this->districtAliases[$dName])) {
                $dName = $this->districtAliases[$dName];
            }

            /** @var District|null $dist */
            $dist = $districts->first(function ($d) use ($dName) {
                return strtolower(trim($d->name)) === $dName;
            });

            $divisionId = $dist?->division_id;
            $districtId = $dist?->id;
            $upazilaId = null;
            $unionId = null;
            $pourashavaId = null;
            $issues = [];

            if ($dist) {
                $rawUpazila = trim($farm->upazila ?? '');
                $uName = strtolower($rawUpazila);

                if ($rawUpazila !== '') {
                    // Match upazila within this district
                    $up = Upazila::where('district_id', $dist->id)
                        ->whereRaw('LOWER(TRIM(name)) = ?', [$uName])
                        ->first();

                    if ($up) {
                        $upazilaId = $up->id;

                        $rawUnion = trim($farm->union ?? '');
                        if ($rawUnion !== '') {
                            $candidates = [
                                strtolower($rawUnion),
                                strtolower(preg_replace('/\s+union$/i', '', $rawUnion)),
                                strtolower(preg_replace('/\s+sadar\s+union$/i', '', $rawUnion)),
                                strtolower(preg_replace('/\s+pourashava$/i', '', $rawUnion)),
                            ];
                            $candidates = array_unique(array_filter($candidates));

                            $un = Union::where('upazila_id', $up->id)
                                ->where(function ($q) use ($candidates) {
                                    foreach ($candidates as $c) {
                                        $q->orWhereRaw('LOWER(TRIM(name)) = ?', [$c]);
                                    }
                                })->first();

                            if ($un) {
                                $unionId = $un->id;
                            } else {
                                $pour = Pourashava::where('upazila_id', $up->id)
                                    ->where(function ($q) use ($candidates) {
                                        foreach ($candidates as $c) {
                                            $q->orWhereRaw('LOWER(TRIM(name)) = ?', [$c]);
                                        }
                                    })->first();

                                if ($pour) {
                                    $pourashavaId = $pour->id;
                                } else {
                                    $issues[] = "Union/Pourashava '{$rawUnion}' not matched in Upazila '{$up->name}'";
                                }
                            }
                        }
                    } else {
                        // Check if upazila exists elsewhere in Bangladesh
                        $otherUp = Upazila::whereRaw('LOWER(TRIM(name)) = ?', [$uName])->with('district')->first();
                        if ($otherUp) {
                            $issues[] = "Upazila '{$rawUpazila}' belongs to District '{$otherUp->district?->name}', but farm is registered in District '{$farm->district}'";
                        } else {
                            $issues[] = "Upazila '{$rawUpazila}' not recognized in District '{$farm->district}'";
                        }
                    }
                }
            } else {
                $issues[] = "District '{$farm->district}' could not be matched to canonical database";
            }

            // Determine match status
            if ($districtId && $upazilaId && ($unionId || $pourashavaId || empty($farm->union))) {
                $stats['fully_matched']++;
            } elseif ($districtId) {
                $stats['partially_matched']++;
            } else {
                $stats['unmatched']++;
            }

            if (!empty($issues)) {
                $flagged[] = [
                    'farm_id' => $farm->id,
                    'farm_name' => $farm->farm_name,
                    'district' => $farm->district,
                    'upazila' => $farm->upazila,
                    'union' => $farm->union,
                    'issues' => $issues,
                ];

                Log::channel('daily')->warning("Farm #{$farm->id} location reconciliation needed: " . implode('; ', $issues));
            }

            if (!$isDryRun) {
                $farm->update([
                    'division_id' => $divisionId,
                    'district_id' => $districtId,
                    'upazila_id' => $upazilaId,
                    'union_id' => $unionId,
                    'pourashava_id' => $pourashavaId,
                ]);
            }
        }

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Farms', $stats['total']],
                ['Fully Matched (All levels matched / confident)', $stats['fully_matched']],
                ['Partially Matched (District matched, Upazila/Union flagged)', $stats['partially_matched']],
                ['Unmatched (District unknown)', $stats['unmatched']],
                ['Total Flagged for Admin Reconciliation', count($flagged)],
            ]
        );

        if (count($flagged) > 0) {
            $this->warn("\nSample flagged farms for admin reconciliation:");
            foreach (array_slice($flagged, 0, 5) as $f) {
                $this->line("  - Farm #{$f['farm_id']} [{$f['farm_name']}]: " . implode(' | ', $f['issues']));
            }
            $this->info("Full details logged and available via the Admin Reconciliation screen.");
        }

        return 0;
    }
}
