<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $products = [
            [
                'name' => 'Mega Aqua Feed Grower 25kg',
                'category' => 'Feed',
                'price' => 2450.00,
                'description' => 'High-protein 32% sinking pellet feed formulated for commercial shrimp and finfish growth stages. Feed 2-3 times daily based on biomass.',
            ],
            [
                'name' => 'Bio-Aqua Probiotic 500g',
                'category' => 'Probiotics',
                'price' => 850.00,
                'description' => 'Multi-strain beneficial bacteria blend for pond bottom bioremediation, organic sludge digestion, and stabilizing water microbiome.',
            ],
            [
                'name' => 'OxyFlow Pond Aerator 2HP',
                'category' => 'Equipment',
                'price' => 18500.00,
                'description' => 'High-efficiency 4-paddle wheel aerator designed for optimal dissolved oxygen transfer in semi-intensive and intensive culture ponds.',
            ],
            [
                'name' => 'AquaClean Water Conditioner 1L',
                'category' => 'Chemicals',
                'price' => 1200.00,
                'description' => 'Fast-acting water clarifier and heavy metal neutralizer. Restores safe pH and alkalinity levels prior to post-larvae stocking.',
            ],
            [
                'name' => 'VitaBoost Growth Promoter 1kg',
                'category' => 'Medicine',
                'price' => 950.00,
                'description' => 'Concentrated essential vitamin and mineral premix. Enhances feed conversion ratio (FCR), carapace hardening, and disease resistance.',
            ],
            [
                'name' => 'Pond Guard Disinfectant 5L',
                'category' => 'Chemicals',
                'price' => 3200.00,
                'description' => 'Broad-spectrum aquaculture pond disinfectant. Eliminates pathogenic bacteria, fungi, and external parasites before stocking.',
            ],
            [
                'name' => 'Zeolite Powder 20kg',
                'category' => 'Chemicals',
                'price' => 650.00,
                'description' => 'Natural mineral pond conditioner. Adsorbs toxic ammonia, hydrogen sulfide, and heavy metals from pond bottoms.',
            ],
            [
                'name' => 'Nursery Fish Feed 10kg',
                'category' => 'Feed',
                'price' => 1400.00,
                'description' => 'Micro-pellet nursery feed with 38% crude protein for fry and fingerling stages. Ensures rapid initial growth and high survival rate.',
            ],
        ];

        $selected = fake()->randomElement($products);

        return [
            'name' => $selected['name'],
            'category' => $selected['category'],
            'price' => $selected['price'],
            'stock' => fake()->numberBetween(20, 200),
            'description' => $selected['description'],
        ];
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Product $product) {
            if ($product->variants()->count() === 0) {
                $product->variants()->create([
                    'variant_label' => 'Standard',
                    'sku' => 'SKU-' . str_pad($product->id, 4, '0', STR_PAD_LEFT) . '-STD',
                    'price' => $product->price,
                    'stock' => (int) ($product->getRawOriginal('stock') ?? 0),
                    'is_default' => true,
                ]);
            }
        });
    }
}
