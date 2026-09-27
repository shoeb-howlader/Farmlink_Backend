<?php

namespace Database\Factories;

use App\Models\Farm;
use App\Models\User;
use App\Models\VetRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VetRecord>
 */
class VetRecordFactory extends Factory
{
    /**
     * Cache generated findings in a run to guarantee zero verbatim duplicates.
     *
     * @var array<string, bool>
     */
    protected static array $usedFindings = [];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $inspectionMethods = [
            'Clinical examination of 35 cast-net sampled Penaeus monodon / Labeo rohita specimens',
            'Microscopic biopsy of branchial gill filaments and hepatopancreas tissue sections',
            'Urgent pond-side clinical triage conducted following sudden juvenile lethargy report',
            'Routine quarterly biosecurity veterinary audit and wet-mount smear evaluation',
            'Post-mortem clinical dissection of moribund juvenile aquaculture stock',
            'Stereomicroscopic evaluation of pleopod and carapace margins on representative samples',
            'Clinical health assessment following heavy diurnal rain and temperature fluctuation',
        ];

        $pathologies = [
            'identified focal gill melanization, filament rot, and secondary filamentous bacterial adhesion',
            'detected early cuticular lesions and lethargic surface swimming with reddish hepatopancreas enlargement',
            'revealed moderate ectoparasite infestation of Zoothamnium and Epistylis clogging respiratory surfaces',
            'showed cloudy whitish muscular necrosis and completely empty digestive gut tract',
            'uncovered severe tail rot necrosis with broken antennae and localized melanin deposits on uropods',
            'indicated acute vibriosis symptoms characterized by luminescent hemolymph and carapace thinning',
            'revealed sluggish benthic foraging, dorsal exoskeleton ulcerations, and gill congestion from organic debris',
            'diagnosed early-stage Black Gill Disease with localized branchial tissue degeneration',
        ];

        $severities = [
            'Morbidity restricted to 3–5% of sampled population; early therapeutic intervention initiated.',
            'Estimated localized mortality at 1.8% in shallow peripheral pond trenches.',
            'Stock condition remains salvageable; immediate bio-containment instituted to prevent canal cross-contamination.',
            'Sub-acute clinical manifestation with active swimming response still retained in central pond waters.',
            'Localized distress exacerbated by benthic hypoxia following consecutive overcast weather.',
            'Moderate clinical vulnerability requiring immediate medical bath and feed medication protocol.',
        ];

        $treatments = [
            'Administered oxytetracycline medicated feed coating at 50 mg/kg biomass for 7 consecutive days alongside pond bath disinfection.',
            'Prescribed potassium permanganate (KMnO4) pond bath at 2.5 ppm and advised immediate 25% bottom-siphon water exchange.',
            'Recommended broadcasting water-soluble Enrofloxacin powder (10 g/kg feed) blended with lipid binder for 5 days.',
            'Applied bio-active iodophor antiseptic pond bath at 0.5 ppm to curb surface fungal spreading and stabilized pond alkalinity.',
            'Prescribed Ciprofloxacin aquaculture suspension (30 ml/100 kg biomass) paired with 24-hour total feed starvation.',
            'Instructed emergency application of veterinary zeolite (50 kg/acre) combined with continuous 12-hour paddle-wheel aeration.',
            'Prescribed botanical tannin disinfectant dip alongside dietary immune-stimulant beta-glucan supplementation for 10 days.',
            'Dosed broad-spectrum florfenicol oral premix at 15 mg/kg biomass and applied agricultural hydrated lime buffer.',
        ];

        $medicines = [
            'Oxytetracycline 20% Feed Grade Premix & Vitamin C',
            'Potassium Permanganate KMnO4 Veterinary Solution',
            'Enrofloxacin 10% Oral Aquaculture Powder',
            'Iodophor Antiseptic Compound & Bio-Shield Disinfectant',
            'Ciprofloxacin 20% Aquaculture Suspension',
            'Aqua-C Immune Vitamin & Zeolite Mineral Conditioner',
            'Florfenicol 10% Medicated Premix & Aqua-Probiotic',
            'Doxycycline Hydrochloride & Beta-Glucan Feed Additive',
            'Povidone-Iodine 10% Solution & Dolomite Buffer',
        ];

        // Ensure unique findings
        $findings = '';
        $maxAttempts = 100;
        $attempt = 0;

        do {
            $method = fake()->randomElement($inspectionMethods);
            $pathology = fake()->randomElement($pathologies);
            $severity = fake()->randomElement($severities);

            $findings = "{$method} {$pathology}. {$severity}";
            $attempt++;
        } while (isset(self::$usedFindings[$findings]) && $attempt < $maxAttempts);

        if (isset(self::$usedFindings[$findings])) {
            $findings .= ' (Diagnostic File #' . fake()->unique()->numerify('VR-####') . ')';
        }

        self::$usedFindings[$findings] = true;

        return [
            'farm_id' => Farm::factory(),
            'vet_id' => User::factory(),
            'visit_date' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'findings' => $findings,
            'treatment' => fake()->randomElement($treatments),
            'medicine_given' => fake()->randomElement($medicines),
            'next_follow_up' => fake()->dateTimeBetween('now', '+2 months')->format('Y-m-d'),
        ];
    }
}
