<?php

namespace Database\Seeders;

use App\Models\Taxonomy;
use Illuminate\Database\Seeder;

class TaxonomySeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Seeds canonical aquaculture taxonomies with bilingual English and Bengali labels.
     * Uses updateOrCreate to ensure idempotency in production environments without disrupting existing relations.
     */
    public function run(): void
    {
        $taxonomies = [
            // ══════════════════════════════════════════════════════════════════════
            // 1. Farm Ownership / Tenancy Type (farm_type)
            // ══════════════════════════════════════════════════════════════════════
            [
                'type' => 'farm_type',
                'slug' => 'own',
                'name' => 'Own Land',
                'name_bn' => 'নিজস্ব জমি / ঘের',
                'description' => 'Privately owned aquaculture estate / নিজস্ব মালিকানাধীন জলাশয় বা ঘের',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'type' => 'farm_type',
                'slug' => 'leased',
                'name' => 'Leased Gher',
                'name_bn' => 'ইজারা / লিজকৃত ঘের',
                'description' => 'Contractual leased gher or water body / চুক্তিবদ্ধ লিজকৃত ঘের বা জলাশয়',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'type' => 'farm_type',
                'slug' => 'family',
                'name' => 'Family Inherited',
                'name_bn' => 'পারিবারিক পৈতৃক ঘের',
                'description' => 'Joint family ancestral holding / পৈতৃক সূত্রে প্রাপ্ত যৌথ বা পারিবারিক ঘের',
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'type' => 'farm_type',
                'slug' => 'partnership',
                'name' => 'Partnership Estate',
                'name_bn' => 'যৌথ অংশীদারিত্বের ঘের',
                'description' => 'Shared commercial aquaculture venture / অংশীদারিত্বের ভিত্তিতে পরিচালিত বাণিজ্যিক ঘের',
                'sort_order' => 4,
                'is_active' => true,
            ],
            [
                'type' => 'farm_type',
                'slug' => 'other',
                'name' => 'Other',
                'name_bn' => 'অন্যান্য ধরন',
                'description' => 'Other landholding arrangement / অন্যান্য মালিকানা কাঠামো',
                'sort_order' => 5,
                'is_active' => true,
            ],

            // ══════════════════════════════════════════════════════════════════════
            // 2. Culture Type / Target Species (culture_type)
            // ══════════════════════════════════════════════════════════════════════
            [
                'type' => 'culture_type',
                'slug' => 'bagda',
                'name' => 'Bagda (Black Tiger Shrimp)',
                'name_bn' => 'বাগদা চিংড়ি (Black Tiger)',
                'description' => 'Penaeus monodon brackish culture / উপকূলীয় লোনা পানির বাগদা চিংড়ি চাষ',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'type' => 'culture_type',
                'slug' => 'golda',
                'name' => 'Golda (Freshwater Prawn)',
                'name_bn' => 'গলদা চিংড়ি (Freshwater Prawn)',
                'description' => 'Macrobrachium rosenbergii freshwater culture / মিঠা পানির গলদা চিংড়ি চাষ',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'type' => 'culture_type',
                'slug' => 'tilapia',
                'name' => 'Tilapia',
                'name_bn' => 'তেলাপিয়া মাছ',
                'description' => 'Oreochromis niloticus finfish culture / দ্রুত বর্ধনশীল বাণিজ্যিক তেলাপিয়া চাষ',
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'type' => 'culture_type',
                'slug' => 'pangas',
                'name' => 'Pangas',
                'name_bn' => 'পাঙ্গাস মাছ',
                'description' => 'Pangasianodon hypophthalmus culture / বাণিজ্যিক পাঙ্গাস চাষ',
                'sort_order' => 4,
                'is_active' => true,
            ],
            [
                'type' => 'culture_type',
                'slug' => 'carp',
                'name' => 'Carp (Rui / Katla / Mrigal)',
                'name_bn' => 'কার্প জাতীয় মাছ (রুই / কাতলা / মৃগেল)',
                'description' => 'Major Indian & Chinese carps polyculture / রুই, কাতলা ও মৃগেল মিশ্র কার্প চাষ',
                'sort_order' => 5,
                'is_active' => true,
            ],
            [
                'type' => 'culture_type',
                'slug' => 'crab',
                'name' => 'Mud Crab',
                'name_bn' => 'শিলা কাঁকড়া',
                'description' => 'Scylla serrata fattening and culture / উপকূলীয় লোনা পানির শিলা কাঁকড়া মোটাতাজাকরণ ও চাষ',
                'sort_order' => 6,
                'is_active' => true,
            ],
            [
                'type' => 'culture_type',
                'slug' => 'mixed',
                'name' => 'Mixed Culture (Fish & Shrimp)',
                'name_bn' => 'মিশ্র মাছ ও চিংড়ি চাষ',
                'description' => 'Polyculture of finfish and crustaceans / সাদা মাছ ও চিংড়ির সমন্বিত চাষ',
                'sort_order' => 7,
                'is_active' => true,
            ],
            [
                'type' => 'culture_type',
                'slug' => 'other',
                'name' => 'Other Species',
                'name_bn' => 'অন্যান্য জলজ প্রজাতি',
                'description' => 'Crab, seabass, or diversified aquatic species / ভেটকি, কুচিয়া বা অন্যান্য জলজ প্রজাতি',
                'sort_order' => 8,
                'is_active' => true,
            ],

            // ══════════════════════════════════════════════════════════════════════
            // 3. Farming System / Technology (farming_system)
            // ══════════════════════════════════════════════════════════════════════
            [
                'type' => 'farming_system',
                'slug' => 'extensive',
                'name' => 'Extensive (Traditional)',
                'name_bn' => 'সনাতন পদ্ধতি (Extensive)',
                'description' => 'Low stocking density, tidal exchange, natural feed / কম ঘনত্ব, জোয়ার-ভাটার প্রাকৃতিক খাদ্য ও পানি নির্ভর',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'type' => 'farming_system',
                'slug' => 'improved_extensive',
                'name' => 'Improved Extensive',
                'name_bn' => 'উন্নত সনাতন পদ্ধতি',
                'description' => 'Moderate stocking with supplementary commercial feed / মধ্যম ঘনত্ব এবং সম্পূরক সুষম খাদ্য প্রয়োগ',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'type' => 'farming_system',
                'slug' => 'semi_intensive',
                'name' => 'Semi-intensive',
                'name_bn' => 'আধা-নিবিড় পদ্ধতি (Semi-intensive)',
                'description' => 'Higher density with commercial feed and water management / নিয়ন্ত্রিত ঘনত্ব, বাণিজ্যিক ফিড ও সার্বক্ষণিক পানি ব্যবস্থাপনা',
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'type' => 'farming_system',
                'slug' => 'intensive',
                'name' => 'Intensive (Aerated)',
                'name_bn' => 'নিবিড় চাষ পদ্ধতি (Intensive)',
                'description' => 'High stocking density with mechanical aeration and biosecurity / প্যাডেল হুইল এয়ারেটর ও বায়োসিকিউরিটি নির্ভর উচ্চ ঘনত্ব চাষ',
                'sort_order' => 4,
                'is_active' => true,
            ],
            [
                'type' => 'farming_system',
                'slug' => 'traditional',
                'name' => 'Traditional Coastal Gher',
                'name_bn' => 'ঐতিহ্যবাহী উপকূলীয় ঘের',
                'description' => 'Traditional coastal seasonal farming / উপকূলের ধান-চিংড়ি ঘূর্ণায়মান ঐতিহ্যবাহী ঘের',
                'sort_order' => 5,
                'is_active' => true,
            ],
            [
                'type' => 'farming_system',
                'slug' => 'other',
                'name' => 'Other System (Biofloc / RAS)',
                'name_bn' => 'অন্যান্য পদ্ধতি (Biofloc / RAS)',
                'description' => 'Biofloc, RAS, or modern recirculating systems / বায়োফ্লক, আরএএস বা আধুনিক পুনঃসঞ্চালন চাষ পদ্ধতি',
                'sort_order' => 6,
                'is_active' => true,
            ],

            // ══════════════════════════════════════════════════════════════════════
            // 4. Main Water Source (water_source)
            // ══════════════════════════════════════════════════════════════════════
            [
                'type' => 'water_source',
                'slug' => 'river',
                'name' => 'River Water',
                'name_bn' => 'প্রাকৃতিক নদী',
                'description' => 'Direct or canalized river flow / প্রাকৃতিক নদীর সরাসরি বা নিয়ন্ত্রিত প্রবাহ',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'type' => 'water_source',
                'slug' => 'canal',
                'name' => 'Canal / Khal',
                'name_bn' => 'খাল / ক্যানেল',
                'description' => 'Irrigation canal or brackish inlet khal / সেচ ক্যানেল বা স্লুইস গেটের প্রবাহমান খাল',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'type' => 'water_source',
                'slug' => 'deep_tubewell',
                'name' => 'Deep Tubewell',
                'name_bn' => 'গভীর নলকূপ',
                'description' => 'Groundwater extraction well / ভূগর্ভস্থ নিরাপদ নলকূপের পানি',
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'type' => 'water_source',
                'slug' => 'rainfed',
                'name' => 'Rainfed Enclosed Pond',
                'name_bn' => 'বৃষ্টির পানি নির্ভর জলাশয়',
                'description' => 'Monsoon rainfall catchment pond / বর্ষার বৃষ্টি এবং পুকুরের সংরক্ষিত পানি',
                'sort_order' => 4,
                'is_active' => true,
            ],
            [
                'type' => 'water_source',
                'slug' => 'tidal_creek',
                'name' => 'Tidal Creek',
                'name_bn' => 'উপকূলীয় জোয়ারের খাঁড়ি / মোহনা',
                'description' => 'Coastal estuary tidal flow / উপকূলীয় মোহনা ও জোয়ার-ভাটার খাঁড়ির পানি',
                'sort_order' => 5,
                'is_active' => true,
            ],
            [
                'type' => 'water_source',
                'slug' => 'other',
                'name' => 'Other Source',
                'name_bn' => 'অন্যান্য পানির উৎস',
                'description' => 'Alternative water source / অন্যান্য বিকল্প পানির উৎস',
                'sort_order' => 6,
                'is_active' => true,
            ],

            // ══════════════════════════════════════════════════════════════════════
            // 5. Available Infrastructure & Facilities (facility)
            // ══════════════════════════════════════════════════════════════════════
            [
                'type' => 'facility',
                'slug' => 'electric',
                'name' => 'Electricity Grid',
                'name_bn' => 'বিদ্যুৎ সংযোগ (পল্লী বিদ্যুৎ)',
                'description' => 'Rural electrification board (REB) power connection / পল্লী বিদ্যুৎ সার্বক্ষণিক সংযোগ',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'type' => 'facility',
                'slug' => 'generator',
                'name' => 'Backup Generator',
                'name_bn' => 'জেনারেটর (ব্যাকআপ বিদ্যুৎ)',
                'description' => 'Diesel/gas generator for uninterrupted aeration / নিরবচ্ছিন্ন এয়ারেশন ও পাম্পের জন্য ডিজেল জেনারেটর',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'type' => 'facility',
                'slug' => 'solar',
                'name' => 'Solar Power System',
                'name_bn' => 'সোলার বিদ্যুৎ ব্যবস্থা',
                'description' => 'Photovoltaic setup for lights and pumps / সৌরবিদ্যুৎ প্যানেল ও ইনভার্টার সিস্টেম',
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'type' => 'facility',
                'slug' => 'pump',
                'name' => 'Water Pump (Low-Lift / Submersible)',
                'name_bn' => 'পানি পাম্প (সেচ ও নিষ্কাশন)',
                'description' => 'Mechanical pumping unit for water intake/drainage / দ্রুত পানি উত্তোলন ও নিষ্কাশন পাম্প',
                'sort_order' => 4,
                'is_active' => true,
            ],
            [
                'type' => 'facility',
                'slug' => 'aerator',
                'name' => 'Paddlewheel Aerator',
                'name_bn' => 'পেডেলহুইল এয়ারেটর',
                'description' => 'Surface or paddlewheel aeration system / পানিতে দ্রবীভূত অক্সিজেন বৃদ্ধির প্যাডেল হুইল এয়ারেটর',
                'sort_order' => 5,
                'is_active' => true,
            ],
            [
                'type' => 'facility',
                'slug' => 'feeding_equip',
                'name' => 'Feeding Equipment / Auto Feeder',
                'name_bn' => 'খাদ্য প্রয়োগ সরঞ্জাম / অটো ফিডার',
                'description' => 'Auto-feeders, feeding trays, or dispensers / খাদ্য ট্রে বা স্বয়ংক্রিয় অটো ফিডার',
                'sort_order' => 6,
                'is_active' => true,
            ],
            [
                'type' => 'facility',
                'slug' => 'storage',
                'name' => 'Feed Storage Shed',
                'name_bn' => 'ফিড ও সামগ্রী গুদামঘর',
                'description' => 'Dry, ventilated room for feeds, medicines, and chemicals / শুষ্ক ও বায়ু চলাচলযুক্ত ফিড ও ওষুধ সংরক্ষণাগার',
                'sort_order' => 7,
                'is_active' => true,
            ],
            [
                'type' => 'facility',
                'slug' => 'net_harvest',
                'name' => 'Harvesting Nets & Traps',
                'name_bn' => 'মাছ ধরার জাল ও আহরণ সরঞ্জাম',
                'description' => 'Cast nets, drag nets, and bamboo traps / ক্ষ্যাপলা জাল, টানা জাল এবং চায়না দুয়ারী বা বাঁশের চাঁই',
                'sort_order' => 8,
                'is_active' => true,
            ],
            [
                'type' => 'facility',
                'slug' => 'fencing',
                'name' => 'Bio-security Fencing / Netting',
                'name_bn' => 'বায়োসিকিউরিটি বেড়া ও জাল বেষ্টনী',
                'description' => 'Perimeter crab/otter fencing and bird netting / কাঁকড়া ও পাখি প্রতিরোধক চারপাশের নেট বেড়া',
                'sort_order' => 9,
                'is_active' => true,
            ],

            // ══════════════════════════════════════════════════════════════════════
            // 6. Product Category (product_category)
            // ══════════════════════════════════════════════════════════════════════
            [
                'type' => 'product_category',
                'slug' => 'feed',
                'name' => 'Aqua Feed & Nutrition',
                'name_bn' => 'মাছের খাদ্য ও পুষ্টি (ফিড)',
                'description' => 'Floating and sinking pellets, starter crumbles, grower feeds / ভাসমান ও ডুবন্ত সুষম দানাদার ফিড',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'type' => 'product_category',
                'slug' => 'medicine',
                'name' => 'Veterinary Medicine & Therapeutics',
                'name_bn' => 'ভেট ওষুধ ও স্বাস্থ্য সামগ্রী',
                'description' => 'Antibacterial, antiparasitic, and vitamins for aquatic health / মাছ ও চিংড়ির রোগ নিরাময়কারী ওষুধ',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'type' => 'product_category',
                'slug' => 'chemicals',
                'name' => 'Water & Soil Chemicals',
                'name_bn' => 'পানি ও মাটি শোধন কেমিক্যাল',
                'description' => 'Limestone, Zeolite, Disinfectants, EDTA / চুন, জিওলাইট, ব্লিচিং ও পানি বিশুদ্ধকারক',
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'type' => 'product_category',
                'slug' => 'equipment',
                'name' => 'Farm Equipment & Tools',
                'name_bn' => 'খামার যন্ত্রপাতি ও টেস্ট কিট',
                'description' => 'Aerators, pumps, water test kits, refractometers / এয়ারেটর, পাম্প ও পানি পরীক্ষার ডিজিটাল কিট',
                'sort_order' => 4,
                'is_active' => true,
            ],
            [
                'type' => 'product_category',
                'slug' => 'probiotics',
                'name' => 'Probiotics & Bioremediators',
                'name_bn' => 'প্রোবায়োটিক ও জৈব প্রতিকারক',
                'description' => 'Nitrifying bacteria, gut probiotics, bottom soil remediators / উপকারী ব্যাকটিরিয়া ও জৈব কাদা নিরাময়ক',
                'sort_order' => 5,
                'is_active' => true,
            ],

            // ══════════════════════════════════════════════════════════════════════
            // 7. Practitioner Specialty Tags (specialty_tag)
            // ══════════════════════════════════════════════════════════════════════
            [
                'type' => 'specialty_tag',
                'slug' => 'water_quality',
                'name' => 'Water Quality Management',
                'name_bn' => 'পানির মান ও পরিবেশ ব্যবস্থাপনা',
                'description' => 'pH, DO, alkalinity, ammonia and nitrite optimization / পিএইচ, দ্রবীভূত অক্সিজেন ও অ্যামোনিয়া অপ্টিমাইজেশন',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'type' => 'specialty_tag',
                'slug' => 'disease_pathology',
                'name' => 'Disease Diagnosis & Pathology',
                'name_bn' => 'রোগ নির্ণয় ও প্যাথলজি',
                'description' => 'Viral (WSSV), bacterial (EMS/AHPND), and fungal diagnoses / ভাইরাস ও ব্যাকটেরিয়া জনিত রোগ নির্ণয়',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'type' => 'specialty_tag',
                'slug' => 'biosecurity',
                'name' => 'Biosecurity & Sanitization',
                'name_bn' => 'বায়োসিকিউরিটি ও খামার জীবাণুমুক্তকরণ',
                'description' => 'Pre-stocking pond prep, sterilization, bird/crab mitigation / পুকুর প্রস্তুতি ও খামার জীবাণুমুক্তকরণ',
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'type' => 'specialty_tag',
                'slug' => 'nutrition',
                'name' => 'Nutrition & Feed Formulation',
                'name_bn' => 'পুষ্টি ও খাদ্য ব্যবস্থাপনা (FCR)',
                'description' => 'Feed conversion ratio (FCR) tuning and gut probiotics / এফসিআর উন্নয়ন ও পুষ্টি ব্যবস্থাপনা',
                'sort_order' => 4,
                'is_active' => true,
            ],
            [
                'type' => 'specialty_tag',
                'slug' => 'nursery_prep',
                'name' => 'Hatchery & Nursery Management',
                'name_bn' => 'হ্যাচারি ও নার্সারি ব্যবস্থাপনা',
                'description' => 'Post-larvae acclimation, nursery nursing, and stocking / পোনা নার্সিং ও মজুত ব্যবস্থাপনা',
                'sort_order' => 5,
                'is_active' => true,
            ],
        ];

        $count = 0;
        foreach ($taxonomies as $item) {
            Taxonomy::updateOrCreate(
                [
                    'type' => $item['type'],
                    'slug' => $item['slug'],
                ],
                [
                    'name' => $item['name'],
                    'name_bn' => $item['name_bn'],
                    'description' => $item['description'],
                    'sort_order' => $item['sort_order'],
                    'is_active' => $item['is_active'],
                ]
            );
            $count++;
        }

        // Flush dynamic caches so newly seeded/updated items immediately take effect
        Taxonomy::clearCache();

        $this->command?->info("✓ TaxonomySeeder: Successfully synced {$count} canonical taxonomies with bilingual English & Bengali attributes.");
    }
}
