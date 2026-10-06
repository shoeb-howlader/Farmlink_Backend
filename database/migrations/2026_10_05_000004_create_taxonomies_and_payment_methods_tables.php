<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('taxonomies', function (Blueprint $table) {
            $table->id();
            $table->string('type', 60)->index(); // farm_type, culture_type, farming_system, water_source, facility, product_category, specialty_tag
            $table->string('slug', 80)->index();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['type', 'slug']);
        });

        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique()->index();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Seed default canonical taxonomies and payment methods
        $now = now();

        $taxonomies = [
            // 1. Farm Type
            ['type' => 'farm_type', 'slug' => 'own', 'name' => 'Own Land', 'description' => 'Privately owned aquaculture estate', 'sort_order' => 1],
            ['type' => 'farm_type', 'slug' => 'leased', 'name' => 'Leased Gher', 'description' => 'Contractual leased gher / water body', 'sort_order' => 2],
            ['type' => 'farm_type', 'slug' => 'family', 'name' => 'Family Inherited', 'description' => 'Joint family ancestral holding', 'sort_order' => 3],
            ['type' => 'farm_type', 'slug' => 'partnership', 'name' => 'Partnership Estate', 'description' => 'Shared commercial venture', 'sort_order' => 4],
            ['type' => 'farm_type', 'slug' => 'other', 'name' => 'Other', 'description' => 'Other landholding arrangement', 'sort_order' => 5],

            // 2. Culture Type
            ['type' => 'culture_type', 'slug' => 'bagda', 'name' => 'Bagda (Black Tiger Shrimp)', 'description' => 'Penaeus monodon brackish culture', 'sort_order' => 1],
            ['type' => 'culture_type', 'slug' => 'golda', 'name' => 'Golda (Freshwater Prawn)', 'description' => 'Macrobrachium rosenbergii freshwater culture', 'sort_order' => 2],
            ['type' => 'culture_type', 'slug' => 'tilapia', 'name' => 'Tilapia', 'description' => 'Oreochromis niloticus finfish culture', 'sort_order' => 3],
            ['type' => 'culture_type', 'slug' => 'pangas', 'name' => 'Pangas', 'description' => 'Pangasianodon hypophthalmus culture', 'sort_order' => 4],
            ['type' => 'culture_type', 'slug' => 'carp', 'name' => 'Carp (Rui / Katla / Mrigal)', 'description' => 'Major Indian & Chinese carps polyculture', 'sort_order' => 5],
            ['type' => 'culture_type', 'slug' => 'other', 'name' => 'Other Species', 'description' => 'Crab, seabass, or diversified aquatic species', 'sort_order' => 6],

            // 3. Farming System
            ['type' => 'farming_system', 'slug' => 'extensive', 'name' => 'Extensive (Traditional)', 'description' => 'Low stocking density, tidal exchange, natural feed', 'sort_order' => 1],
            ['type' => 'farming_system', 'slug' => 'improved_extensive', 'name' => 'Improved Extensive', 'description' => 'Moderate stocking with supplementary feed', 'sort_order' => 2],
            ['type' => 'farming_system', 'slug' => 'semi_intensive', 'name' => 'Semi-intensive', 'description' => 'Higher density with commercial feed and water management', 'sort_order' => 3],
            ['type' => 'farming_system', 'slug' => 'intensive', 'name' => 'Intensive (Aerated)', 'description' => 'High density with continuous mechanical aeration and biosecurity', 'sort_order' => 4],
            ['type' => 'farming_system', 'slug' => 'other', 'name' => 'Other System', 'description' => 'Biofloc, RAS, or experimental systems', 'sort_order' => 5],

            // 4. Main Water Source
            ['type' => 'water_source', 'slug' => 'river', 'name' => 'River Water', 'description' => 'Direct or canalized river flow', 'sort_order' => 1],
            ['type' => 'water_source', 'slug' => 'canal', 'name' => 'Canal / Khal', 'description' => 'Irrigation canal or brackish inlet khal', 'sort_order' => 2],
            ['type' => 'water_source', 'slug' => 'deep_tubewell', 'name' => 'Deep Tubewell', 'description' => 'Groundwater extraction well', 'sort_order' => 3],
            ['type' => 'water_source', 'slug' => 'rainfed', 'name' => 'Rainfed Enclosed', 'description' => 'Monsoon rainfall catchment pond', 'sort_order' => 4],
            ['type' => 'water_source', 'slug' => 'tidal_creek', 'name' => 'Tidal Creek', 'description' => 'Coastal estuary tidal flow', 'sort_order' => 5],
            ['type' => 'water_source', 'slug' => 'other', 'name' => 'Other Source', 'description' => 'Municipal or alternative water supply', 'sort_order' => 6],

            // 5. Available Facilities
            ['type' => 'facility', 'slug' => 'electric', 'name' => 'Electricity Grid', 'description' => 'Rural electrification board (REB) power connection', 'sort_order' => 1],
            ['type' => 'facility', 'slug' => 'generator', 'name' => 'Backup Generator', 'description' => 'Diesel/gas generator for uninterrupted aeration', 'sort_order' => 2],
            ['type' => 'facility', 'slug' => 'solar', 'name' => 'Solar Power System', 'description' => 'Photovoltaic setup for lights and pumps', 'sort_order' => 3],
            ['type' => 'facility', 'slug' => 'pump', 'name' => 'Water Pump (Low-Lift / Submersible)', 'description' => 'Mechanical pumping unit for water intake/drainage', 'sort_order' => 4],
            ['type' => 'facility', 'slug' => 'aerator', 'name' => 'Paddlewheel Aerator', 'description' => 'Surface or venturi aeration system', 'sort_order' => 5],
            ['type' => 'facility', 'slug' => 'feeding_equip', 'name' => 'Feeding Equipment', 'description' => 'Auto-feeders, feeding trays, or dispensers', 'sort_order' => 6],
            ['type' => 'facility', 'slug' => 'storage', 'name' => 'Storage Shed', 'description' => 'Dry, ventilated room for feeds, medicines, and chemicals', 'sort_order' => 7],
            ['type' => 'facility', 'slug' => 'net_harvest', 'name' => 'Harvesting Nets & Traps', 'description' => 'Cast nets, drag nets, and bamboo traps', 'sort_order' => 8],
            ['type' => 'facility', 'slug' => 'fencing', 'name' => 'Bio-security Fencing / Netting', 'description' => 'Perimeter crab/otter fencing and bird netting', 'sort_order' => 9],

            // 6. Product Category
            ['type' => 'product_category', 'slug' => 'feed', 'name' => 'Aqua Feed & Nutrition', 'description' => 'Floating and sinking pellets, starter crumbles, grower feeds', 'sort_order' => 1],
            ['type' => 'product_category', 'slug' => 'medicine', 'name' => 'Veterinary Medicine & Therapeutics', 'description' => 'Antibacterial, antiparasitic, and vitamins for aquatic health', 'sort_order' => 2],
            ['type' => 'product_category', 'slug' => 'chemicals', 'name' => 'Water & Soil Chemicals', 'description' => 'Limestone (Dolomite, Quicklime), Zeolite, Disinfectants', 'sort_order' => 3],
            ['type' => 'product_category', 'slug' => 'equipment', 'name' => 'Farm Equipment & Tools', 'description' => 'Aerators, pumps, water test kits, refractometers', 'sort_order' => 4],
            ['type' => 'product_category', 'slug' => 'probiotics', 'name' => 'Probiotics & Bioremediators', 'description' => 'Nitrifying bacteria, gut probiotics, bottom soil remediators', 'sort_order' => 5],

            // 7. Specialty Tags
            ['type' => 'specialty_tag', 'slug' => 'water_quality', 'name' => 'Water Quality Management', 'description' => 'pH, DO, alkalinity, ammonia and nitrite optimization', 'sort_order' => 1],
            ['type' => 'specialty_tag', 'slug' => 'disease_pathology', 'name' => 'Disease Diagnosis & Pathology', 'description' => 'Viral (WSSV), bacterial (EMS/AHPND), and fungal diagnoses', 'sort_order' => 2],
            ['type' => 'specialty_tag', 'slug' => 'biosecurity', 'name' => 'Biosecurity & Sanitization', 'description' => 'Pre-stocking pond prep, sterilization, bird/crab mitigation', 'sort_order' => 3],
            ['type' => 'specialty_tag', 'slug' => 'nutrition', 'name' => 'Nutrition & Feed Formulation', 'description' => 'Feed conversion ratio (FCR) tuning and gut probiotics', 'sort_order' => 4],
            ['type' => 'specialty_tag', 'slug' => 'nursery_prep', 'name' => 'Hatchery & Nursery Management', 'description' => 'Post-larvae acclimation, nursery nursing, and stocking', 'sort_order' => 5],
        ];

        foreach ($taxonomies as $item) {
            DB::table('taxonomies')->insert(array_merge($item, [
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        $paymentMethods = [
            ['code' => 'cash', 'name' => 'Cash at Depot', 'description' => 'Direct cash payment at depot or counter', 'is_active' => true, 'sort_order' => 1],
            ['code' => 'cod', 'name' => 'Cash on Delivery (COD)', 'description' => 'Pay when products arrive at farm', 'is_active' => true, 'sort_order' => 2],
            ['code' => 'farmer_credit', 'name' => 'Farmer Credit Account', 'description' => 'Pay via approved credit ledger arrangement', 'is_active' => true, 'sort_order' => 3],
            ['code' => 'bkash', 'name' => 'bKash Mobile Banking', 'description' => 'Merchant digital wallet payment', 'is_active' => false, 'sort_order' => 4],
            ['code' => 'nagad', 'name' => 'Nagad Mobile Banking', 'description' => 'Merchant digital wallet payment', 'is_active' => false, 'sort_order' => 5],
        ];

        foreach ($paymentMethods as $pm) {
            DB::table('payment_methods')->insert(array_merge($pm, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('taxonomies');
    }
};
