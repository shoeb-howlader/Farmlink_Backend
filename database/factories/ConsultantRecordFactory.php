<?php

namespace Database\Factories;

use App\Models\ConsultantRecord;
use App\Models\Farm;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConsultantRecord>
 */
class ConsultantRecordFactory extends Factory
{
    /**
     * Cache generated recommendations in a run to guarantee zero verbatim duplicates.
     *
     * @var array<string, bool>
     */
    protected static array $usedRecommendations = [];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $focusAreas = [
            'Pond #1 nursery segment',
            'Main grow-out pond #2',
            'Broodstock holding tank',
            'South canal feeder dyke',
            'Perimeter nursery enclosure',
            'Primary polyculture pond',
            'High-density shrimp pond #3',
            'Fingerling acclimation basin',
            'Benthic sedimentation section',
            'Pond #4 earthen culture trench',
            'Secondary fingerling rearing pond',
            'Central nursery raceway',
        ];

        $conditions = [
            'elevated unionized ammonia (NH3) at 0.38 ppm and morning dissolved oxygen dipping to 3.4 mg/L',
            'sub-optimal Secchi disk transparency (down to 18 cm) indicating excessive blue-green algae bloom',
            'water alkalinity dipping below 90 ppm with late-afternoon pH swinging up to 8.9',
            'benthic anaerobic black sludge accumulation exceeding 6 cm along the peripheral feeding trench',
            'sluggish post-larval feeding response and delayed carapace hardening post-molting cycle',
            'high water turbidity following torrential monsoon runoff causing acute gill siltation stress',
            'elevated nitrite (NO2-) concentration at 1.2 ppm following three continuous overcast days',
            'erratic plankton crash with pond water turning translucent brownish-grey and foul odor',
            'overcrowding stress and high coefficient of variation in cast-net size sampling',
            'salinity dropping abruptly from 14 ppt to 6 ppt due to unbuffered rainwater inflow',
            'excessive organic loading from uneaten broadcast feed decomposing along the bottom dyke',
            'sub-surface thermal stratification with bottom water temperature lagging 3.5°C behind surface',
        ];

        $actions = [
            'advised applying agricultural dolomite at 35 kg/decimal alongside 4 kg/decimal activated zeolite powder',
            'recommended reducing daily feeding ration by 20% and extending nocturnal paddle-wheel aeration to 8 continuous hours',
            'suggested broadcasting Bacillus subtilis probiotic inoculants mixed with 5 kg fermented sugarcane molasses',
            'instructed performing a gradual 15% bottom-siphon water exchange from the settled bio-security reservoir',
            'advised adjusting stocking density to 14–18 PL/m² and installing fine nylon perimeter crab-barrier fencing',
            'recommended supplementing feed with Aqua-C ascorbic acid (5g/kg feed) to boost stress tolerance during turnover',
            'advised deploying four feeding check-trays positioned 5 meters off dyke edges for 90-minute feeding audits',
            'suggested quicklime broadcasting (CaO at 15 kg/acre) during pre-dawn hours to buffer diurnal pH swings',
            'recommended implementing strict biosecurity foot-baths with potassium permanganate at pond access bunds',
            'advised introducing commercial biofloc molasses carbon supplementation to restore optimal 12:1 C:N ratio',
            'recommended installing overhead mono-filament nylon grid lines to prevent predatory cormorant and kingfisher entry',
            'advised switching feed formulation to 34% crude protein extruded sinking pellets with multi-enzyme binder',
        ];

        $followUps = [
            'Schedule follow-up water parameter testing in 72 hours; withhold broadcast feed if tray residuals exceed 10%.',
            'Inspect cast-net specimens at 7-day mark to confirm carapace hardening and full gut fullness index.',
            'Monitor pre-dawn DO levels daily using digital probe; maintain mechanical aeration until DO exceeds 5.0 mg/L.',
            'Review alkalinity levels 48 hours post-liming; target stable 120-140 ppm CaCO3 equivalent.',
            'Verify sludge evacuation via central drainage pipe prior to next scheduled bi-weekly water top-up.',
            'Re-evaluate feed conversion ratio (FCR) and weekly average daily gain (ADG) at next scheduled agronomy audit.',
            'Ensure continuous monitoring of surface foam dispersion; conduct repeat microscopic plankton count in 5 days.',
            'Audit biomass estimate after two weeks of modified feeding schedule to adjust feeding table accurately.',
        ];

        // Generate a non-colliding recommendation
        $recommendation = '';
        $maxAttempts = 100;
        $attempt = 0;

        do {
            $focus = fake()->randomElement($focusAreas);
            $cond = fake()->randomElement($conditions);
            $act = fake()->randomElement($actions);
            $fol = fake()->randomElement($followUps);

            $recommendation = "Inspection of {$focus} revealed {$cond}. Specialist {$act}. {$fol}";
            $attempt++;
        } while (isset(self::$usedRecommendations[$recommendation]) && $attempt < $maxAttempts);

        if (isset(self::$usedRecommendations[$recommendation])) {
            $recommendation .= ' (Case Ref #' . fake()->unique()->numerify('CR-####') . ')';
        }

        self::$usedRecommendations[$recommendation] = true;

        return [
            'farm_id' => Farm::factory(),
            'consultant_id' => User::factory(),
            'visit_date' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'recommendation' => $recommendation,
            'next_follow_up' => fake()->dateTimeBetween('now', '+2 months')->format('Y-m-d'),
        ];
    }
}
