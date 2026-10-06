<?php

namespace Database\Seeders;

use App\Models\ConsultantRecord;
use App\Models\Farm;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\VetRecord;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Seed Roles
        $this->call(RoleSeeder::class);

        // 2. Admin User
        $admin = User::firstOrCreate(
            ['email' => 'admin@farmlink.com'],
            [
                'name' => 'Farmlink Admin',
                'phone' => '01700000001',
                'district' => 'Dhaka',
                'gender' => 'male',
                'password' => Hash::make('password'),
            ]
        );
        $admin->syncRoles(['admin']);

        // 3. DEO User
        $deo = User::firstOrCreate(
            ['email' => 'deo@farmlink.com'],
            [
                'name' => 'Farmlink DEO Officer',
                'phone' => '01700000002',
                'district' => 'Satkhira',
                'gender' => 'female',
                'password' => Hash::make('password'),
            ]
        );
        $deo->syncRoles(['data_entry_operator']);

        // 4. Veterinary Doctor User
        $vet = User::firstOrCreate(
            ['email' => 'vet@farmlink.com'],
            [
                'name' => 'Dr. Rafiqul Islam (Vet)',
                'phone' => '01700000003',
                'district' => 'Khulna',
                'gender' => 'male',
                'password' => Hash::make('password'),
            ]
        );
        $vet->syncRoles(['veterinary_doctor']);

        // 5. Consultant User
        $consultant = User::firstOrCreate(
            ['email' => 'consultant@farmlink.com'],
            [
                'name' => 'Md. Hasan Ali (Consultant)',
                'phone' => '01700000004',
                'district' => 'Bagerhat',
                'gender' => 'male',
                'password' => Hash::make('password'),
            ]
        );
        $consultant->syncRoles(['consultant']);

        // 6. Products (25 distinct aquaculture products across 5 categories)
        $this->call(ProductSeeder::class);
        $products = Product::all();

        // 7. Demo Farmer User
        $demoFarmer = User::firstOrCreate(
            ['email' => 'farmer@farmlink.com'],
            [
                'name' => 'Abdul Karim (Farmer)',
                'phone' => '01712345678',
                'district' => 'Satkhira',
                'gender' => 'male',
                'password' => Hash::make('password'),
            ]
        );
        $demoFarmer->syncRoles(['farmer']);

        // Demo Farmer Farm & Service Requests
        $demoFarm = Farm::firstOrCreate(
            ['user_id' => $demoFarmer->id, 'farm_name' => 'Karim Shrimp Farm'],
            [
                'farm_type' => 'Own',
                'total_area' => 120.50,
                'pond_count' => 4,
                'cultivation_area' => 95.00,
                'district' => 'Satkhira',
                'upazila' => 'Debhata',
                'main_culture_type' => 'Shrimp',
                'farming_system' => 'Semi-Intensive',
                'water_exchange_facility' => 'Yes',
            ]
        );

        // 7b. Self-Registered Farmer User (Verified Phone, Active)
        $pendingFarmer = User::firstOrCreate(
            ['email' => 'hasanuzzaman@farmlink.com'],
            [
                'name' => 'Hasanuzzaman Molla',
                'phone' => '01987654321',
                'district' => 'Satkhira',
                'gender' => 'male',
                'status' => 'active',
                'phone_verified_at' => now()->subHours(4),
                'password' => Hash::make('password'),
            ]
        );
        $pendingFarmer->syncRoles(['farmer']);

        Farm::firstOrCreate(
            ['user_id' => $pendingFarmer->id, 'farm_name' => 'Molla Coastal Prawn Pond'],
            [
                'farm_type' => 'Leased',
                'total_area' => 45.00,
                'pond_count' => 2,
                'cultivation_area' => 38.00,
                'district' => 'Satkhira',
                'upazila' => 'Assasuni',
                'main_culture_type' => 'Golda',
                'farming_system' => 'Improved Extensive',
                'water_exchange_facility' => 'Yes',
            ]
        );

        // 1 Pending Urgent Vet Request (created 30h ago, so overdue > 24h SLA)
        \App\Models\ServiceRequest::firstOrCreate(
            ['farm_id' => $demoFarm->id, 'description' => 'White spot symptoms observed in Pond #2. Urgent veterinary diagnosis and treatment plan required.'],
            [
                'farmer_id' => $demoFarmer->id,
                'type' => 'vet',
                'urgency' => 'urgent',
                'status' => 'pending',
                'created_at' => now()->subHours(30),
            ]
        );

        // 1 Assigned Normal Consultant Request
        \App\Models\ServiceRequest::firstOrCreate(
            ['farm_id' => $demoFarm->id, 'description' => 'Advisory requested for post-larvae stocking density and natural pond feed management.'],
            [
                'farmer_id' => $demoFarmer->id,
                'type' => 'consultant',
                'urgency' => 'normal',
                'status' => 'assigned',
                'assigned_to' => $consultant->id,
                'assigned_at' => now()->subHours(6),
                'created_at' => now()->subHours(12),
            ]
        );

        // 1 Completed Vet Request
        $completedVetRec = VetRecord::firstOrCreate(
            ['farm_id' => $demoFarm->id, 'findings' => 'Bacterial gill necrosis identified in nursery pond.'],
            [
                'vet_id' => $vet->id,
                'visit_date' => now()->subDays(2)->toDateString(),
                'treatment' => 'Administered antibiotic water treatment and advised 25% water exchange.',
                'medicine_given' => 'Oxytetracycline 20% solution',
                'next_follow_up' => now()->addDays(5)->toDateString(),
            ]
        );

        $demoPrescription = \App\Models\Prescription::firstOrCreate(
            ['vet_record_id' => $completedVetRec->id],
            ['notes' => 'Complete antibacterial protocol for nursery pond gill necrosis.']
        );
        if ($demoPrescription->items()->count() === 0) {
            $oxyProduct = Product::where('name', 'like', '%Oxy%')->first();
            $aquaCleanProduct = Product::where('name', 'like', '%AquaClean%')->first();

            \App\Models\PrescriptionItem::create([
                'prescription_id' => $demoPrescription->id,
                'medicine_name' => 'Oxytetracycline 20% Solution',
                'dosage' => '50mg / kg biomass',
                'frequency' => 'Once daily',
                'duration' => '5 days',
                'instructions' => 'Mix thoroughly with morning feed pellets. Maintain strong aeration.',
                'product_id' => $oxyProduct?->id,
            ]);

            \App\Models\PrescriptionItem::create([
                'prescription_id' => $demoPrescription->id,
                'medicine_name' => 'AquaClean Water Conditioner 1L',
                'dosage' => '100ml / decimal',
                'frequency' => 'Single application',
                'duration' => '1 day',
                'instructions' => 'Dilute in bucket of pond water and distribute evenly across nursery pond.',
                'product_id' => $aquaCleanProduct?->id,
            ]);
        }

        \App\Models\ServiceRequest::firstOrCreate(
            ['farm_id' => $demoFarm->id, 'description' => 'Discolored gills and slow feeding behavior in nursery pond.'],
            [
                'farmer_id' => $demoFarmer->id,
                'type' => 'vet',
                'urgency' => 'urgent',
                'status' => 'completed',
                'assigned_to' => $vet->id,
                'created_at' => now()->subDays(4),
                'assigned_at' => now()->subDays(3),
                'completed_at' => now()->subDays(2),
                'fulfilled_record_type' => VetRecord::class,
                'fulfilled_record_id' => $completedVetRec->id,
                'rating' => 5,
                'feedback_note' => 'Dr. Rafiqul responded very quickly and the gill infection resolved within 48 hours. Excellent service!',
            ]
        );

        // 8. Additional Farmers with Farms, Orders, and Records
        $farmers = User::factory(5)->create()->each(function (User $farmer) use ($vet, $consultant, $products) {
            $farmer->syncRoles(['farmer']);

            // Create 1-2 farms for each farmer
            $farms = Farm::factory(rand(1, 2))->create([
                'user_id' => $farmer->id,
                'district' => $farmer->district,
            ]);

            // Add vet & consultant records to each farm and create matching completed ServiceRequests
            foreach ($farms as $farm) {
                $vr = VetRecord::factory()->create([
                    'farm_id' => $farm->id,
                    'vet_id' => $vet->id,
                ]);

                \App\Models\ServiceRequest::create([
                    'farm_id' => $farm->id,
                    'farmer_id' => $farmer->id,
                    'type' => 'vet',
                    'description' => $vr->findings ?: 'Veterinary clinical examination and pond treatment protocol.',
                    'urgency' => 'normal',
                    'status' => 'completed',
                    'source_channel' => 'self_service',
                    'assigned_to' => $vet->id,
                    'assigned_at' => $vr->visit_date ? \Carbon\Carbon::parse($vr->visit_date)->subHours(12) : now()->subDays(3),
                    'completed_at' => $vr->visit_date ? \Carbon\Carbon::parse($vr->visit_date) : now()->subDays(2),
                    'fulfilled_record_type' => VetRecord::class,
                    'fulfilled_record_id' => $vr->id,
                    'created_at' => $vr->visit_date ? \Carbon\Carbon::parse($vr->visit_date)->subDays(1) : now()->subDays(4),
                ]);

                $cr = ConsultantRecord::factory()->create([
                    'farm_id' => $farm->id,
                    'consultant_id' => $consultant->id,
                ]);

                \App\Models\ServiceRequest::create([
                    'farm_id' => $farm->id,
                    'farmer_id' => $farmer->id,
                    'type' => 'consultant',
                    'description' => $cr->recommendation ?: 'Aquaculture pond management and biosecurity advisory consultation.',
                    'urgency' => 'normal',
                    'status' => 'completed',
                    'source_channel' => 'self_service',
                    'assigned_to' => $consultant->id,
                    'assigned_at' => $cr->visit_date ? \Carbon\Carbon::parse($cr->visit_date)->subHours(12) : now()->subDays(3),
                    'completed_at' => $cr->visit_date ? \Carbon\Carbon::parse($cr->visit_date) : now()->subDays(2),
                    'fulfilled_record_type' => ConsultantRecord::class,
                    'fulfilled_record_id' => $cr->id,
                    'created_at' => $cr->visit_date ? \Carbon\Carbon::parse($cr->visit_date)->subDays(1) : now()->subDays(4),
                ]);
            }

            // Create an order with items for this farmer
            $order = Order::factory()->create([
                'user_id' => $farmer->id,
                'status' => 'confirmed',
                'total' => 0,
            ]);

            $total = 0;
            foreach ($products->random(2) as $product) {
                $qty = rand(1, 3);
                $price = $product->price;
                $lineTotal = $price * $qty;
                $total += $lineTotal;

                OrderItem::factory()->create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $qty,
                    'price' => $price,
                ]);
            }

            $order->update(['total' => $total]);
        });

        // 8. Seed Realistic Notifications
        $this->call(NotificationSeeder::class);

        // 9. Seed Product Reviews & Delivered Orders
        $this->call(ProductReviewSeeder::class);

        // 10. Geocode data & Pourashavas
        $this->call(BengaliGeocodeSeeder::class);
        $this->call(PourashavaSeeder::class);
    }
}

