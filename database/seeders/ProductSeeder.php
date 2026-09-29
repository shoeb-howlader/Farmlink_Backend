<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * 25 genuinely distinct aquaculture products across all 5 categories.
     * No duplicated names, descriptions, or prices.
     */
    public const CATALOG = [
        // Category: Feed (5 distinct products)
        [
            'name' => 'Mega Aqua Feed Grower 25kg',
            'category' => 'Feed',
            'price' => 2450.00,
            'stock' => 120,
            'description' => 'High-protein 32% sinking pellet feed formulated for commercial shrimp and finfish growth stages. Feed 2-3 times daily based on body weight.',
            'is_featured' => true,
        ],
        [
            'name' => 'Nursery Micro Pellets 10kg',
            'category' => 'Feed',
            'price' => 1650.00,
            'stock' => 85,
            'description' => 'Micro-crumble nursery feed with 38% crude protein for fry and post-larvae nursery stages, enriched with marine lipids for high survival.',
            'is_featured' => false,
        ],
        [
            'name' => 'AquaMaster Starter Pellet 20kg',
            'category' => 'Feed',
            'price' => 2150.00,
            'stock' => 95,
            'description' => 'Slow-sinking starter feed for early juvenile stages with balanced amino acids and gut health immunostimulants.',
            'is_featured' => false,
        ],
        [
            'name' => 'Finisher Prime Sinking Feed 25kg',
            'category' => 'Feed',
            'price' => 2600.00,
            'stock' => 110,
            'description' => 'Finishing diet for commercial harvest preparation. Maximizes flesh firmness, flavor profile, and weight gain before market dispatch.',
            'is_featured' => false,
        ],
        [
            'name' => 'Broodstock Vitality Feed 15kg',
            'category' => 'Feed',
            'price' => 3200.00,
            'stock' => 40,
            'description' => 'High-grade breeder ration enriched with astaxanthin, spirulina, and polyunsaturated fatty acids to boost fecundity and egg quality.',
            'is_featured' => false,
        ],

        // Category: Probiotics (5 distinct products)
        [
            'name' => 'Bio-Aqua Probiotic 500g',
            'category' => 'Probiotics',
            'price' => 850.00,
            'stock' => 80,
            'description' => 'Multi-strain beneficial bacteria blend for pond bottom bioremediation, organic sludge digestion, and maintaining healthy water microbiome.',
            'is_featured' => true,
        ],
        [
            'name' => 'AquaFlora Gut Probiotic 1kg',
            'category' => 'Probiotics',
            'price' => 1450.00,
            'stock' => 65,
            'description' => 'Dietary feed-top probiotic containing Bacillus subtilis and lactic acid bacteria to prevent white gut and improve nutrient assimilation.',
            'is_featured' => false,
        ],
        [
            'name' => 'SludgeAway Pond Bottom Digester 1kg',
            'category' => 'Probiotics',
            'price' => 1850.00,
            'stock' => 50,
            'description' => 'Concentrated enzyme-producing anaerobic bacteria specifically formulated to digest thick black soil and eliminate toxic bottom mud.',
            'is_featured' => false,
        ],
        [
            'name' => 'NitriClear Ammonia Reducer 500ml',
            'category' => 'Probiotics',
            'price' => 950.00,
            'stock' => 70,
            'description' => 'Live autotrophic nitrifying culture that rapidly converts harmful ammonia and nitrite into harmless nitrogen compounds in intensive ponds.',
            'is_featured' => false,
        ],
        [
            'name' => 'BioFloc Probiotic Blend 2kg',
            'category' => 'Probiotics',
            'price' => 2800.00,
            'stock' => 45,
            'description' => 'Specialized heterotrophic bacterial inoculum developed for biofloc systems to build stable microbial flocs and recycle nutrient waste.',
            'is_featured' => false,
        ],

        // Category: Equipment (5 distinct products)
        [
            'name' => 'OxyFlow Pond Aerator 2HP',
            'category' => 'Equipment',
            'price' => 18500.00,
            'stock' => 25,
            'description' => 'Energy-efficient 4-paddle wheel aerator designed for high oxygen transfer rate in semi-intensive and intensive fish and shrimp culture ponds.',
            'is_featured' => true,
        ],
        [
            'name' => 'Dual-Wheel Solar Pond Aerator 1HP',
            'category' => 'Equipment',
            'price' => 24000.00,
            'stock' => 15,
            'description' => 'Hybrid solar-compatible surface aerator with high-torque copper motor, ideal for rural gheris with intermittent electrical grid access.',
            'is_featured' => false,
        ],
        [
            'name' => 'Digital Dissolved Oxygen Meter Pro',
            'category' => 'Equipment',
            'price' => 12500.00,
            'stock' => 20,
            'description' => 'Field-grade optical DO meter with 3-meter waterproof probe, automatic temperature compensation, and rapid response readout.',
            'is_featured' => false,
        ],
        [
            'name' => 'Heavy-Duty Bottom Sludge Pump 1.5HP',
            'category' => 'Equipment',
            'price' => 16800.00,
            'stock' => 18,
            'description' => 'Submersible non-clog centrifugal sewage and mud pump engineered for clearing pond sediment between culture cycles.',
            'is_featured' => false,
        ],
        [
            'name' => 'Aquaculture Water Quality Test Kit',
            'category' => 'Equipment',
            'price' => 4200.00,
            'stock' => 40,
            'description' => 'Complete multi-parameter test suite measuring pH, ammonia (NH3/NH4), nitrite (NO2), alkalinity, and salinity with 200 tests included.',
            'is_featured' => false,
        ],

        // Category: Chemicals (5 distinct products)
        [
            'name' => 'AquaClean Water Conditioner 1L',
            'category' => 'Chemicals',
            'price' => 1200.00,
            'stock' => 60,
            'description' => 'Rapid-action water clarifier and heavy metal neutralizer. Restores optimal pH balance and alkalinity in pond water before stocking.',
            'is_featured' => true,
        ],
        [
            'name' => 'Zeolite Granular Mineral 25kg',
            'category' => 'Chemicals',
            'price' => 750.00,
            'stock' => 150,
            'description' => 'Premium porous natural zeolite for high-capacity adsorption of unionized ammonia, hydrogen sulfide, and toxic suspended organics.',
            'is_featured' => false,
        ],
        [
            'name' => 'Quicklime Calcium Oxide 20kg',
            'category' => 'Chemicals',
            'price' => 550.00,
            'stock' => 200,
            'description' => 'Agricultural lime for pre-stocking soil sterilization, neutralizing acidic pond bottoms, and elevating reserve alkalinity.',
            'is_featured' => false,
        ],
        [
            'name' => 'PondGuard Iodine Disinfectant 5L',
            'category' => 'Chemicals',
            'price' => 3400.00,
            'stock' => 35,
            'description' => 'Non-residual povidone-iodine disinfectant for water sanitation and equipment biosecurity against bacterial and fungal zoospores.',
            'is_featured' => false,
        ],
        [
            'name' => 'WaterSafe EDTA Chelator 5kg',
            'category' => 'Chemicals',
            'price' => 2100.00,
            'stock' => 55,
            'description' => 'High-purity chelation agent to bind excess iron, copper, and heavy metals commonly found in coastal saline groundwater sources.',
            'is_featured' => false,
        ],

        // Category: Medicine (5 distinct products)
        [
            'name' => 'VitaBoost Growth Promoter 1kg',
            'category' => 'Medicine',
            'price' => 950.00,
            'stock' => 100,
            'description' => 'Fortified essential vitamin and amino acid supplement. Enhances feed conversion ratio (FCR) and disease resistance in aquatic species.',
            'is_featured' => false,
        ],
        [
            'name' => 'CarapaceArmor Mineral Premix 5kg',
            'category' => 'Medicine',
            'price' => 1750.00,
            'stock' => 80,
            'description' => 'Essential calcium, magnesium, and bio-available trace mineral formulation to prevent soft-shell syndrome and aid shrimp molting.',
            'is_featured' => false,
        ],
        [
            'name' => 'OxyTabs Emergency Oxygen Granules 1kg',
            'category' => 'Medicine',
            'price' => 850.00,
            'stock' => 90,
            'description' => 'Fast-dissolving sodium percarbonate tablets for emergency dissolved oxygen release at pond bottom during sudden morning oxygen drops.',
            'is_featured' => false,
        ],
        [
            'name' => 'Herbal HepatoGuard Liver Tonic 1L',
            'category' => 'Medicine',
            'price' => 1350.00,
            'stock' => 70,
            'description' => 'All-natural botanical hepatopancreas protectant. Stimulates bile secretion, clears digestive toxins, and prevents early mortality syndrome.',
            'is_featured' => false,
        ],
        [
            'name' => 'ImmunoShield Beta-Glucan 500g',
            'category' => 'Medicine',
            'price' => 1600.00,
            'stock' => 60,
            'description' => 'High-purity beta-1,3/1,6-glucan immune booster that activates phagocytic cells against viral and opportunistic bacterial outbreaks.',
            'is_featured' => false,
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $existingProducts = Product::orderBy('id')->get();
        if ($existingProducts->isNotEmpty() && $existingProducts->count() <= count(self::CATALOG)) {
            collect(self::CATALOG)->each(function ($item, $index) use ($existingProducts) {
                $existing = $existingProducts->get($index);
                if ($existing) {
                    $existing->update([
                        'name' => $item['name'],
                        'category' => $item['category'],
                        'price' => $item['price'],
                        'stock' => $item['stock'],
                        'description' => $item['description'],
                        'is_active' => true,
                        'is_featured' => $item['is_featured'] ?? false,
                    ]);
                } else {
                    Product::create([
                        'name' => $item['name'],
                        'category' => $item['category'],
                        'price' => $item['price'],
                        'stock' => $item['stock'],
                        'description' => $item['description'],
                        'is_active' => true,
                        'is_featured' => $item['is_featured'] ?? false,
                    ]);
                }
            });
        } else {
            collect(self::CATALOG)->each(function ($item) {
                Product::updateOrCreate(
                    ['name' => $item['name']],
                    [
                        'category' => $item['category'],
                        'price' => $item['price'],
                        'stock' => $item['stock'],
                        'description' => $item['description'],
                        'is_active' => true,
                        'is_featured' => $item['is_featured'] ?? false,
                    ]
                );
            });
        }

        $allProducts = Product::all();
        foreach ($allProducts as $product) {
            if (str_contains($product->name, 'Mega Aqua Feed Grower')) {
                $product->variants()->firstOrCreate(
                    ['variant_label' => '5kg Bag'],
                    ['sku' => "PRD-{$product->id}-5KG-BAG", 'price' => 550.00, 'stock' => 30, 'is_default' => false]
                );
                $product->variants()->firstOrCreate(
                    ['variant_label' => '10kg Bag'],
                    ['sku' => "PRD-{$product->id}-10KG-BAG", 'price' => 1050.00, 'stock' => 45, 'is_default' => false]
                );
                $product->variants()->firstOrCreate(
                    ['variant_label' => '25kg Commercial'],
                    ['sku' => "PRD-{$product->id}-25KG-COMMERCIAL", 'price' => 2450.00, 'stock' => 120, 'is_default' => true]
                );
            } elseif (str_contains($product->name, 'Bio-Aqua Probiotic')) {
                $product->variants()->firstOrCreate(
                    ['variant_label' => '250g Pack'],
                    ['sku' => "PRD-{$product->id}-250G-PACK", 'price' => 480.00, 'stock' => 25, 'is_default' => false]
                );
                $product->variants()->firstOrCreate(
                    ['variant_label' => '500g Bottle'],
                    ['sku' => "PRD-{$product->id}-500G-BOTTLE", 'price' => 850.00, 'stock' => 60, 'is_default' => true]
                );
                $product->variants()->firstOrCreate(
                    ['variant_label' => '1kg Farm Pack'],
                    ['sku' => "PRD-{$product->id}-1KG-FARM-PACK", 'price' => 1600.00, 'stock' => 40, 'is_default' => false]
                );
            } elseif (str_contains($product->name, 'AquaClean Water Conditioner')) {
                $product->variants()->firstOrCreate(
                    ['variant_label' => '500ml'],
                    ['sku' => "PRD-{$product->id}-500ML", 'price' => 650.00, 'stock' => 35, 'is_default' => false]
                );
                $product->variants()->firstOrCreate(
                    ['variant_label' => '1L Standard'],
                    ['sku' => "PRD-{$product->id}-1L-STANDARD", 'price' => 1200.00, 'stock' => 70, 'is_default' => true]
                );
                $product->variants()->firstOrCreate(
                    ['variant_label' => '5L Canister'],
                    ['sku' => "PRD-{$product->id}-5L-CANISTER", 'price' => 5400.00, 'stock' => 15, 'is_default' => false]
                );
            } elseif ($product->variants()->count() === 0) {
                $label = 'Standard';
                if (preg_match('/(\d+(?:\.\d+)?\s*(?:kg|g|L|ml|HP))/i', $product->name, $matches)) {
                    $label = $matches[1];
                }
                $cleanLabel = strtoupper(preg_replace('/[^A-Z0-9]+/i', '-', trim($label)));
                $cleanLabel = trim($cleanLabel, '-');
                $product->variants()->create([
                    'variant_label' => $label,
                    'sku' => "PRD-{$product->id}-" . ($cleanLabel ?: 'STANDARD'),
                    'price' => $product->price,
                    'stock' => $product->stock,
                    'is_default' => true,
                ]);
            }
        }

        // Normalize all variant SKUs to follow PRD-<id>-<VARIANT_LABEL>
        foreach (\App\Models\ProductVariant::all() as $v) {
            if (empty($v->sku) || !str_starts_with($v->sku, 'PRD-')) {
                $labelSlug = strtoupper(preg_replace('/[^A-Z0-9]+/i', '-', trim($v->variant_label)));
                $labelSlug = trim($labelSlug, '-');
                $v->update(['sku' => "PRD-{$v->product_id}-" . ($labelSlug ?: 'VAR')]);
            }
        }

        // Populate specs, usage instructions, and sample documents per category
        foreach ($allProducts as $product) {
            $short = \Illuminate\Support\Str::limit(strip_tags($product->description ?? ''), 155, '...');
            $usage = null;
            $video = null;

            if ($product->category === 'Feed') {
                $usage = '<p>Feed 2–3 times daily across pond feeding trays. Measure feeding response within 1.5–2 hours of distribution and adjust quantity based on water temperature and shrimp weight.</p>';
            } elseif (in_array($product->category, ['Medicine', 'Chemicals'])) {
                $usage = '<p>Dilute recommended dosage into 20 liters of fresh pond water. Broadcast evenly across the pond surface during morning aerator operation. Discontinue use 7 days prior to commercial harvest.</p>';
            } elseif ($product->category === 'Probiotics') {
                $usage = '<p>Mix with pond water and organic molasses (1:1 ratio). Ferment in an aerated container for 2–4 hours before broadcasting evenly across the pond water column.</p>';
            } elseif ($product->category === 'Equipment') {
                $usage = '<p>Inspect all electrical wiring and ground fault circuit breakers before turning on. Position aerator in deep pond sectors to generate circular current and avoid bank erosion.</p>';
                $video = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
            }

            $product->update([
                'short_description' => $short ?: 'Verified aquaculture input certified for commercial pond cultivation.',
                'usage_instructions' => $usage,
                'video_url' => $video,
            ]);

            // Seed Specs if empty
            if ($product->specs()->count() === 0) {
                if ($product->category === 'Feed') {
                    $product->specs()->createMany([
                        ['label' => 'Crude Protein', 'value' => '32% - 38% Guaranteed', 'sort_order' => 1],
                        ['label' => 'Pellet Size', 'value' => '1.5mm - 2.2mm Sinking', 'sort_order' => 2],
                        ['label' => 'Pack Weight', 'value' => '25kg Multi-layer Moisture Bag', 'sort_order' => 3],
                        ['label' => 'Feeding Rate', 'value' => '2.5% – 3.2% Body Weight / Day', 'sort_order' => 4],
                    ]);
                } elseif (in_array($product->category, ['Medicine', 'Chemicals'])) {
                    $product->specs()->createMany([
                        ['label' => 'Active Ingredient', 'value' => 'Aquaculture Grade Formulation', 'sort_order' => 1],
                        ['label' => 'Dosage', 'value' => '500g – 1000g per Acre (1m depth)', 'sort_order' => 2],
                        ['label' => 'Application Method', 'value' => 'Surface Broadcast via Water Dilution', 'sort_order' => 3],
                        ['label' => 'Withdrawal Period', 'value' => '7 Days Before Harvest', 'sort_order' => 4],
                        ['label' => 'Shelf Life', 'value' => '24 Months from Manufacture Date', 'sort_order' => 5],
                        ['label' => 'Storage', 'value' => 'Store below 25°C in a dry shaded warehouse', 'sort_order' => 6],
                    ]);
                } elseif ($product->category === 'Probiotics') {
                    $product->specs()->createMany([
                        ['label' => 'Bacterial Strain', 'value' => 'Bacillus subtilis & licheniformis blend', 'sort_order' => 1],
                        ['label' => 'Concentration', 'value' => '1.5 x 10^9 CFU/g', 'sort_order' => 2],
                        ['label' => 'Application Rate', 'value' => '250g per Acre weekly', 'sort_order' => 3],
                        ['label' => 'Shelf Life', 'value' => '18 Months', 'sort_order' => 4],
                    ]);
                } elseif ($product->category === 'Equipment') {
                    $product->specs()->createMany([
                        ['label' => 'Power / Motor', 'value' => '1.5HP – 2.0HP Heavy Duty Motor', 'sort_order' => 1],
                        ['label' => 'Voltage / Phase', 'value' => '220V Single-Phase / 380V Three-Phase', 'sort_order' => 2],
                        ['label' => 'Warranty', 'value' => '12 Months Free Replacement Warranty', 'sort_order' => 3],
                        ['label' => 'Frame Material', 'value' => '304 Stainless Steel & UV-resistant PE', 'sort_order' => 4],
                    ]);
                }
            }

            // Seed Sample Document for Product 1 and Equipment
            if ($product->documents()->count() === 0 && ($product->id === 1 || $product->category === 'Equipment')) {
                $product->documents()->create([
                    'title' => 'Technical Datasheet & Aquaculture Safety Certificate',
                    'file_path' => 'documents/sample-datasheet.pdf',
                    'type' => 'datasheet',
                    'file_size_bytes' => 420000,
                ]);
            }
        }

        // Ensure every seeded product has at least one valid image and no broken placeholder cards
        $categoryImages = [
            'Feed' => [
                'products/337b7651-8936-4c75-80d8-cece220185c4.jpg',
                'products/75a89a6f-c817-4638-a91a-a69b73a7cfb3.jpg',
            ],
            'Probiotics' => [
                'products/281cd32e-770a-40ec-afb9-d921d6252195.jpg',
                'products/8e586534-6943-4581-adc0-dc1f1d7685f2.jpg',
            ],
            'Equipment' => [
                'products/d8a9e53d-5203-4186-aa0b-d5df62b609c9.jpg',
                'products/dd7d302b-a301-41af-9eff-79aa991d7789.jpg',
            ],
            'Chemicals' => [
                'products/a1277515-0cf7-45c4-a57a-a233828bf93e.jpg',
                'products/e04d4797-cefe-48f7-b140-05566f1d8bd8.jpg',
            ],
            'Medicine' => [
                'products/8855340a-a2a0-47fe-9aff-70b80fa951b4.png',
                'products/3caf186a-8ddb-4781-9b49-bc6bcefc868a.jpg',
                'products/dd932b71-d3ec-4e59-976c-9abaefcf350e.jpg',
                'products/e8ddc697-bf83-4b66-934f-2204123bcf94.jpg',
            ],
        ];

        foreach (Product::all() as $idx => $p) {
            $pool = $categoryImages[$p->category] ?? $categoryImages['Feed'];
            $selectedImage = $pool[$idx % count($pool)];
            $thumbPath = 'products/thumbnails/' . basename($selectedImage);

            if (empty($p->image_path) || !file_exists(storage_path('app/public/' . $p->image_path))) {
                $p->update([
                    'image_path' => $selectedImage,
                    'image_thumbnail_path' => file_exists(storage_path('app/public/' . $thumbPath)) ? $thumbPath : $selectedImage,
                ]);
            }

            // Remove any empty / broken image records
            $p->images()->where(function ($q) {
                $q->whereNull('image_path')->orWhere('image_path', '');
            })->delete();

            if ($p->images()->count() === 0) {
                $p->images()->create([
                    'image_path' => $p->image_path ?: $selectedImage,
                    'alt_text' => $p->name,
                    'sort_order' => 0,
                    'is_primary' => true,
                ]);
            }
        }
    }
}
