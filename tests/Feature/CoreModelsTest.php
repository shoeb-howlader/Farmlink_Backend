<?php

use App\Models\ConsultantRecord;
use App\Models\Farm;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\VetRecord;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('roles can be seeded and assigned to users', function () {
    $this->seed(RoleSeeder::class);

    expect(Role::where('name', 'admin')->exists())->toBeTrue()
        ->and(Role::where('name', 'data_entry_operator')->exists())->toBeTrue()
        ->and(Role::where('name', 'veterinary_doctor')->exists())->toBeTrue()
        ->and(Role::where('name', 'consultant')->exists())->toBeTrue()
        ->and(Role::where('name', 'farmer')->exists())->toBeTrue();

    $farmer = User::factory()->create();
    $farmer->assignRole('farmer');

    expect($farmer->hasRole('farmer'))->toBeTrue()
        ->and($farmer->hasRole('admin'))->toBeFalse();
});

test('farms can be created with all 23 form fields and relationships', function () {
    $farmer = User::factory()->create([
        'phone' => '01712345678',
        'district' => 'Satkhira',
    ]);

    $farm = Farm::factory()->create([
        'user_id' => $farmer->id,
        'farm_name' => 'Green Delta Aqua Farm',
        'farm_type' => 'Own',
        'total_area' => 12.50,
        'pond_count' => 4,
        'cultivation_area' => 10.00,
        'district' => 'Satkhira',
        'upazila' => 'Shyamnagar',
        'union' => 'Burigoalini',
        'village' => 'Datinakhali',
        'farm_address' => 'Near Forest Station',
        'gps_lat' => 22.1852000,
        'gps_lng' => 89.2450000,
        'aquaculture_experience_years' => 8,
        'previous_farming_experience' => 'Shrimp',
        'main_culture_type' => 'Bagda',
        'farming_system' => 'Semi-intensive',
        'main_water_source' => 'River',
        'water_exchange_facility' => 'Yes',
        'water_source_distance' => '50m',
        'available_facilities' => ['Electric', 'Generator', 'Aerator'],
        'aerator_count' => 2,
        'aerator_hp' => '2 HP',
        'aerator_hours_per_day' => 8.0,
        'farm_manager' => 'Self',
        'technical_support_used' => 'Local Vet',
        'main_advice_source' => 'Feed Dealer',
    ]);

    expect($farm->user->id)->toBe($farmer->id)
        ->and($farmer->farms)->toHaveCount(1)
        ->and($farm->farm_name)->toBe('Green Delta Aqua Farm')
        ->and($farm->available_facilities)->toBe(['Electric', 'Generator', 'Aerator'])
        ->and($farm->aerator_count)->toBe(2)
        ->and($farm->aerator_hp)->toBe('2 HP');
});

test('orders and order items link correctly to users and products', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create([
        'name' => 'Mega Aqua Feed',
        'price' => 2500.00,
        'stock' => 50,
    ]);

    $order = Order::factory()->create([
        'user_id' => $user->id,
        'status' => 'pending',
        'total' => 5000.00,
    ]);

    $item = OrderItem::factory()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'price' => 2500.00,
    ]);

    expect($order->user->id)->toBe($user->id)
        ->and($order->items)->toHaveCount(1)
        ->and($item->product->id)->toBe($product->id)
        ->and((float) $item->price)->toBe(2500.00);
});

test('vet and consultant records link properly to farms and users', function () {
    $farmer = User::factory()->create();
    $vet = User::factory()->create();
    $consultant = User::factory()->create();
    $farm = Farm::factory()->create(['user_id' => $farmer->id]);

    $vetRecord = VetRecord::factory()->create([
        'farm_id' => $farm->id,
        'vet_id' => $vet->id,
        'findings' => 'White spot disease suspected',
        'treatment' => 'Administer disinfectants and stop water exchange',
    ]);

    $consultantRecord = ConsultantRecord::factory()->create([
        'farm_id' => $farm->id,
        'consultant_id' => $consultant->id,
        'recommendation' => 'Reduce feeding rate by 20% and increase aeration',
    ]);

    expect($vetRecord->farm->id)->toBe($farm->id)
        ->and($vetRecord->vet->id)->toBe($vet->id)
        ->and($consultantRecord->farm->id)->toBe($farm->id)
        ->and($consultantRecord->consultant->id)->toBe($consultant->id)
        ->and($farm->vetRecords)->toHaveCount(1)
        ->and($farm->consultantRecords)->toHaveCount(1);
});
