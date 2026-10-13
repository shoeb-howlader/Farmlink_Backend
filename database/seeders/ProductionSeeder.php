<?php

namespace Database\Seeders;

use App\Models\Division;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ProductionSeeder extends Seeder
{
    /**
     * Run the production-safe database seeds.
     * This seeder only populates essential master data, catalogs, roles, geocodes,
     * and platform defaults without injecting fake demo orders, test farmers, or dummy notifications.
     */
    public function run(): void
    {
        $this->command?->info('1. Seeding system roles & permissions...');
        $this->call(RoleSeeder::class);

        $this->command?->info('2. Checking & seeding Bangladesh Geocode administrative hierarchy...');
        if (Division::count() === 0) {
            $this->call(\Devfaysal\BangladeshGeocode\Seeders\DivisionSeeder::class);
            $this->call(\Devfaysal\BangladeshGeocode\Seeders\DistrictSeeder::class);
            $this->call(\Devfaysal\BangladeshGeocode\Seeders\UpazilaSeeder::class);
            $this->call(\Devfaysal\BangladeshGeocode\Seeders\UnionSeeder::class);
        }

        $this->command?->info('3. Seeding Bengali names for divisions, districts, and upazilas...');
        $this->call(BengaliGeocodeSeeder::class);

        $this->command?->info('4. Seeding Pourashavas (Municipalities)...');
        $this->call(PourashavaSeeder::class);

        $this->command?->info('5. Seeding Aquaculture Catalog Products, Specifications & Variants...');
        $this->call(ProductSeeder::class);

        $this->command?->info('6. Seeding Bilingual (English & Bengali) Product Content...');
        $this->call(BilingualProductSeeder::class);

        $this->command?->info('7. Seeding Canned Support & Clinical Consultation Responses...');
        $this->call(CannedResponseSeeder::class);

        $this->command?->info('8. Ensuring Initial Super-Admin Account exists...');
        $adminEmail = env('ADMIN_DEFAULT_EMAIL', 'admin@farmlink.com');
        $adminPhone = env('ADMIN_DEFAULT_PHONE', '01700000001');
        $adminPassword = env('ADMIN_DEFAULT_PASSWORD', 'password');

        $admin = User::firstOrCreate(
            ['email' => $adminEmail],
            [
                'name' => 'Farmlink Admin',
                'phone' => $adminPhone,
                'district' => 'Dhaka',
                'gender' => 'male',
                'password' => Hash::make($adminPassword),
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
                'is_active' => true,
                'status' => 'active',
            ]
        );

        if (!$admin->hasRole('admin')) {
            $admin->assignRole('admin');
        }

        $this->command?->info("✓ Production master data seeding complete. Admin verified: {$admin->email}");
    }
}
