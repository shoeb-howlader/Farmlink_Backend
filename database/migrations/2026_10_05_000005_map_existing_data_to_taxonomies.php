<?php

use App\Models\ActivityLog;
use App\Models\Farm;
use App\Models\Product;
use App\Models\Taxonomy;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run data migration mapping existing records' current values into taxonomy slugs,
     * flagging any record whose value doesn't cleanly match an expected option for manual admin review.
     */
    public function up(): void
    {
        // 1. Map Farms farm_type
        $farmTypeTaxonomies = Taxonomy::byType('farm_type')->get()->keyBy(fn ($t) => strtolower(trim($t->slug)));
        foreach (Farm::cursor() as $farm) {
            if (! empty($farm->farm_type)) {
                $rawVal = trim($farm->farm_type);
                $normalized = strtolower(str_replace(' ', '_', $rawVal));

                // Direct or alias matches
                $matchedSlug = match ($normalized) {
                    'own', 'own_land' => 'own',
                    'leased', 'leased_gher' => 'leased',
                    'family', 'family_inherited' => 'family',
                    'partnership', 'partnership_estate' => 'partnership',
                    'other' => 'other',
                    default => null,
                };

                if ($matchedSlug && $farmTypeTaxonomies->has($matchedSlug)) {
                    $farm->farm_type = $matchedSlug;
                    $farm->saveQuietly();
                } else {
                    ActivityLog::log(
                        'taxonomy.migration_review_flagged',
                        $farm,
                        [
                            'table' => 'farms',
                            'record_id' => $farm->id,
                            'field' => 'farm_type',
                            'unmatched_value' => $rawVal,
                            'reason' => 'Does not cleanly match any canonical farm_type taxonomy option. Flagged for admin review.',
                        ]
                    );
                }
            }

            // 2. Map main_culture_type
            if (! empty($farm->main_culture_type)) {
                $rawVal = trim($farm->main_culture_type);
                $normalized = strtolower(str_replace(' ', '_', $rawVal));

                $matchedCulture = match ($normalized) {
                    'bagda', 'black_tiger_shrimp' => 'bagda',
                    'golda', 'freshwater_prawn' => 'golda',
                    'tilapia' => 'tilapia',
                    'pangas', 'pangasius' => 'pangas',
                    'carp' => 'carp',
                    'other', 'other_species' => 'other',
                    default => null,
                };

                if ($matchedCulture) {
                    $farm->main_culture_type = $matchedCulture;
                    $farm->saveQuietly();
                } else {
                    ActivityLog::log(
                        'taxonomy.migration_review_flagged',
                        $farm,
                        [
                            'table' => 'farms',
                            'record_id' => $farm->id,
                            'field' => 'main_culture_type',
                            'unmatched_value' => $rawVal,
                            'reason' => 'Ambiguous or unmapped culture type (e.g. generic Shrimp without Bagda/Golda distinction). Flagged for admin review.',
                        ]
                    );
                }
            }

            // 3. Map farming_system
            if (! empty($farm->farming_system)) {
                $rawVal = trim($farm->farming_system);
                $normalized = strtolower(str_replace([' ', '-'], '_', $rawVal));

                $matchedSystem = match ($normalized) {
                    'extensive', 'traditional' => 'extensive',
                    'improved_extensive' => 'improved_extensive',
                    'semi_intensive' => 'semi_intensive',
                    'intensive' => 'intensive',
                    'other' => 'other',
                    default => null,
                };

                if ($matchedSystem) {
                    $farm->farming_system = $matchedSystem;
                    $farm->saveQuietly();
                } else {
                    ActivityLog::log(
                        'taxonomy.migration_review_flagged',
                        $farm,
                        [
                            'table' => 'farms',
                            'record_id' => $farm->id,
                            'field' => 'farming_system',
                            'unmatched_value' => $rawVal,
                            'reason' => 'Unmatched farming system. Flagged for admin review.',
                        ]
                    );
                }
            }

            // 4. Map main_water_source
            if (! empty($farm->main_water_source)) {
                $rawVal = trim($farm->main_water_source);
                $normalized = strtolower(str_replace([' ', '/', '-'], '_', $rawVal));

                $matchedSource = match ($normalized) {
                    'river' => 'river',
                    'canal', 'khal', 'canal_khal' => 'canal',
                    'deep_tubewell', 'tubewell' => 'deep_tubewell',
                    'rainfed' => 'rainfed',
                    'tidal_creek' => 'tidal_creek',
                    'other' => 'other',
                    default => null,
                };

                if ($matchedSource) {
                    $farm->main_water_source = $matchedSource;
                    $farm->saveQuietly();
                } else {
                    ActivityLog::log(
                        'taxonomy.migration_review_flagged',
                        $farm,
                        [
                            'table' => 'farms',
                            'record_id' => $farm->id,
                            'field' => 'main_water_source',
                            'unmatched_value' => $rawVal,
                            'reason' => 'Compound or unmapped water source. Flagged for admin review.',
                        ]
                    );
                }
            }
        }

        // 5. Map Products category
        foreach (Product::cursor() as $product) {
            if (! empty($product->category)) {
                $rawCategory = trim($product->category);
                $normalizedCat = strtolower(str_replace(' ', '_', $rawCategory));

                $matchedCat = match ($normalizedCat) {
                    'feed' => 'feed',
                    'medicine' => 'medicine',
                    'chemicals' => 'chemicals',
                    'equipment' => 'equipment',
                    'probiotics' => 'probiotics',
                    default => null,
                };

                if ($matchedCat) {
                    $product->category = $matchedCat;
                    $product->saveQuietly();
                } else {
                    ActivityLog::log(
                        'taxonomy.migration_review_flagged',
                        $product,
                        [
                            'table' => 'products',
                            'record_id' => $product->id,
                            'field' => 'category',
                            'unmatched_value' => $rawCategory,
                            'reason' => 'Unmatched product category. Flagged for admin review.',
                        ]
                    );
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Data mapping is idempotent and does not require destructive reversal.
    }
};
