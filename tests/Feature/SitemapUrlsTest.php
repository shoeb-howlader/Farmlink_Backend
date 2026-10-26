<?php

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('sitemap urls endpoint returns public active product urls', function () {
    Product::factory()->create([
        'name' => 'Active Fish Feed',
        'is_active' => true,
    ]);

    Product::factory()->create([
        'name' => 'Inactive Chemical',
        'is_active' => false,
    ]);

    $response = $this->getJson('/api/sitemap-urls');

    $response->assertStatus(200);
    $data = $response->json();

    expect($data)->toBeArray()
        ->and(count($data))->toBe(1)
        ->and($data[0]['loc'])->toContain('/products/')
        ->and($data[0]['priority'])->toBe(0.8)
        ->and($data[0]['changefreq'])->toBe('weekly');
});

test('sitemap urls works under v1 prefix as well', function () {
    $response = $this->getJson('/api/v1/sitemap-urls');
    $response->assertStatus(200);
    expect($response->json())->toBeArray();
});
