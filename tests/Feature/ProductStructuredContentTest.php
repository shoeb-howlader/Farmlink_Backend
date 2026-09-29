<?php

use App\Models\Product;
use App\Models\User;
use App\Services\RichTextSanitizer;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('rich text sanitizer strips script tags and event handler attributes', function () {
    $dirty = '<div>
        <h2>Safe Title</h2>
        <p>This is <strong>safe</strong> and contains a <a href="https://example.com">link</a>.</p>
        <script>alert("xss attack!");</script>
        <img src="test.jpg" onerror="alert(1)" onload="evil()" alt="Aquaculture supply">
        <a href="javascript:alert(document.cookie)">Malicious Link</a>
        <table><thead><tr><th>Nutrient</th></tr></thead><tbody><tr><td>Protein 32%</td></tr></tbody></table>
    </div>';

    $cleaned = RichTextSanitizer::sanitize($dirty);

    // Verify script tags and contents are stripped
    expect($cleaned)->not->toContain('<script>');
    expect($cleaned)->not->toContain('alert("xss attack!");');
    expect($cleaned)->not->toContain('</script>');

    // Verify event-handler attributes (onerror, onload) are stripped
    expect($cleaned)->not->toContain('onerror');
    expect($cleaned)->not->toContain('onload');

    // Verify javascript: URLs are stripped
    expect($cleaned)->not->toContain('javascript:');

    // Verify safe tags are retained
    expect($cleaned)->toContain('<h2>Safe Title</h2>');
    expect($cleaned)->toContain('<strong>safe</strong>');
    expect($cleaned)->toContain('<table>');
    expect($cleaned)->toContain('<td>Protein 32%</td>');
});

test('admin product store and update sanitizes rich text description and usage instructions', function () {
    $admin = User::factory()->create(['email' => 'admin@test.com']);
    $admin->syncRoles(['admin']);
    Sanctum::actingAs($admin);

    $dirtyDesc = '<p>Feed instructions</p><script>alert("attack");</script><p onmouseover="bad()">Pond bottom feed</p>';
    $dirtyUsage = '<h3>Usage Guidelines</h3><img src="safe.png" onerror="steal()" /><p>Feed 2x daily</p>';

    $response = $this->postJson('/api/v1/admin/products', [
        'name' => 'Bio-Cleanse Probiotic',
        'category' => 'Probiotics',
        'price' => 1200.00,
        'stock' => 50,
        'description' => $dirtyDesc,
        'usage_instructions' => $dirtyUsage,
        'short_description' => 'Concentrated probiotic for shrimp ponds.',
        'specs' => [
            ['label' => 'Bacillus Count', 'value' => '1x10^9 CFU/g', 'sort_order' => 1],
            ['label' => 'Packaging', 'value' => '500g pouch', 'sort_order' => 2],
        ],
    ]);

    $response->assertStatus(201);
    $productId = $response->json('data.id');

    $product = Product::with(['specs'])->find($productId);
    expect($product)->not->toBeNull();

    // Verify sanitized in DB
    expect($product->description)->not->toContain('<script>');
    expect($product->description)->not->toContain('alert("attack");');
    expect($product->description)->not->toContain('onmouseover');
    expect($product->description)->toContain('<p>Feed instructions</p>');

    expect($product->usage_instructions)->not->toContain('onerror');
    expect($product->usage_instructions)->toContain('<h3>Usage Guidelines</h3>');
    expect($product->usage_instructions)->toContain('<p>Feed 2x daily</p>');

    // Verify specs were created
    expect($product->specs()->count())->toBe(2);
    expect($product->specs()->first()->label)->toBe('Bacillus Count');
});

test('admin can upload and delete product pdf documents', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['email' => 'admin2@test.com']);
    $admin->syncRoles(['admin']);
    Sanctum::actingAs($admin);

    $product = Product::create([
        'name' => 'Digital Dissolved Oxygen Meter Pro',
        'category' => 'Equipment',
        'price' => 8500.00,
        'stock' => 15,
        'description' => 'Precision oxygen meter.',
        'is_active' => true,
    ]);

    $pdfFile = UploadedFile::fake()->create('manual.pdf', 500, 'application/pdf');

    $uploadRes = $this->postJson("/api/v1/admin/products/{$product->id}/documents", [
        'file' => $pdfFile,
        'title' => 'Technical User Manual & Calibration Guide',
        'type' => 'datasheet',
    ]);

    $uploadRes->assertStatus(201)
        ->assertJsonPath('data.title', 'Technical User Manual & Calibration Guide')
        ->assertJsonPath('data.type', 'datasheet');

    $docId = $uploadRes->json('data.id');
    expect($product->documents()->count())->toBe(1);

    // Delete document
    $deleteRes = $this->deleteJson("/api/v1/admin/products/{$product->id}/documents/{$docId}");
    $deleteRes->assertStatus(200);

    expect($product->documents()->count())->toBe(0);
});

test('product detail API exposes structured content, rating distribution, and review pack size', function () {
    $farmer = User::factory()->create(['name' => 'Habib Farmer', 'district' => 'Satkhira']);
    $farmer->syncRoles(['farmer']);

    $product = Product::create([
        'name' => 'AquaClean Water Conditioner',
        'category' => 'Chemicals',
        'price' => 750.00,
        'stock' => 100,
        'short_description' => 'Fast acting water conditioner for brackish ponds.',
        'description' => '<p>Eliminates heavy metals and neutralizes chlorine.</p>',
        'usage_instructions' => '<p>Apply 1L per acre of water surface.</p>',
        'is_active' => true,
    ]);

    $v = $product->variants()->create([
        'variant_label' => '1L Bottle',
        'sku' => 'AC-1L',
        'price' => 750.00,
        'stock' => 100,
        'is_default' => true,
    ]);

    $product->specs()->create([
        'label' => 'Active Ingredient',
        'value' => 'Sodium Thiosulfate & EDTA blend',
        'sort_order' => 1,
    ]);

    $order = \App\Models\Order::create([
        'user_id' => $farmer->id,
        'status' => 'delivered',
        'channel' => 'storefront',
        'payment_mode' => 'cash_on_delivery',
        'total' => 750.00,
    ]);

    \App\Models\OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_variant_id' => $v->id,
        'quantity' => 1,
        'price_at_purchase' => 750.00,
    ]);

    \App\Models\ProductReview::create([
        'product_id' => $product->id,
        'user_id' => $farmer->id,
        'order_id' => $order->id,
        'rating' => 5,
        'comment' => 'Remarkable water transparency restoration.',
    ]);

    // Test public product detail API
    $response = $this->getJson("/api/v1/products/{$product->id}");

    $response->assertStatus(200)
        ->assertJsonPath('data.short_description', 'Fast acting water conditioner for brackish ponds.')
        ->assertJsonPath('data.usage_instructions', '<p>Apply 1L per acre of water surface.</p>')
        ->assertJsonPath('data.specs.0.label', 'Active Ingredient')
        ->assertJsonPath('data.rating_distribution.5', 1)
        ->assertJsonPath('data.rating_distribution.4', 0)
        ->assertJsonPath('data.reviews.0.variant_label', '1L Bottle')
        ->assertJsonPath('data.reviews.0.reviewer_district', 'Satkhira');
});
