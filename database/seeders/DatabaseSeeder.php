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

        // 6. Demo Farmer User
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

        // 6b. Pending Approval Farmer User (Self-Registered, Verified Phone, Awaiting Admin Approval)
        $pendingFarmer = User::firstOrCreate(
            ['email' => 'hasanuzzaman@farmlink.com'],
            [
                'name' => 'Hasanuzzaman Molla',
                'phone' => '01987654321',
                'district' => 'Satkhira',
                'gender' => 'male',
                'status' => 'pending_approval',
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

        // 7. Products
        $products = collect([
            Product::factory()->create([
                'name' => 'Mega Aqua Feed Grower 25kg',
                'category' => 'Feed',
                'price' => 2450.00,
                'stock' => 120,
                'description' => 'High-protein 32% sinking pellet feed formulated for commercial shrimp and finfish growth stages. Feed 2-3 times daily based on body weight.',
            ]),
            Product::factory()->create([
                'name' => 'Bio-Aqua Probiotic 500g',
                'category' => 'Probiotics',
                'price' => 850.00,
                'stock' => 80,
                'description' => 'Multi-strain beneficial bacteria blend for pond bottom bioremediation, organic sludge digestion, and maintaining healthy water microbiome.',
            ]),
            Product::factory()->create([
                'name' => 'OxyFlow Pond Aerator 2HP',
                'category' => 'Equipment',
                'price' => 18500.00,
                'stock' => 25,
                'description' => 'Energy-efficient 4-paddle wheel aerator designed for high oxygen transfer rate in semi-intensive and intensive fish and shrimp culture ponds.',
            ]),
            Product::factory()->create([
                'name' => 'AquaClean Water Conditioner 1L',
                'category' => 'Chemicals',
                'price' => 1200.00,
                'stock' => 60,
                'description' => 'Rapid-action water clarifier and heavy metal neutralizer. Restores optimal pH balance and alkalinity in pond water before stocking.',
            ]),
            Product::factory()->create([
                'name' => 'VitaBoost Growth Promoter 1kg',
                'category' => 'Medicine',
                'price' => 950.00,
                'stock' => 100,
                'description' => 'Fortified essential vitamin and amino acid supplement. Enhances feed conversion ratio (FCR) and disease resistance in aquatic species.',
            ]),
        ]);

        // 7. Farmers with Farms, Orders, and Records
        $farmers = User::factory(5)->create()->each(function (User $farmer) use ($vet, $consultant, $products) {
            $farmer->syncRoles(['farmer']);

            // Create 1-2 farms for each farmer
            $farms = Farm::factory(rand(1, 2))->create([
                'user_id' => $farmer->id,
                'district' => $farmer->district,
            ]);

            // Add vet & consultant records to each farm
            foreach ($farms as $farm) {
                VetRecord::factory()->create([
                    'farm_id' => $farm->id,
                    'vet_id' => $vet->id,
                ]);

                ConsultantRecord::factory()->create([
                    'farm_id' => $farm->id,
                    'consultant_id' => $consultant->id,
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
    }
}
