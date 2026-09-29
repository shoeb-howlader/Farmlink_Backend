<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProductReviewSeeder extends Seeder
{
    /**
     * Run the database seeds for product reviews.
     */
    public function run(): void
    {
        $demoFarmer = User::where('email', 'farmer@farmlink.com')->first();
        if (! $demoFarmer) {
            $demoFarmer = User::role('farmer')->first();
        }

        $otherFarmers = User::role('farmer')
            ->when($demoFarmer, fn ($q) => $q->where('id', '!=', $demoFarmer->id))
            ->take(5)
            ->get();

        $products = Product::with('variants')->take(8)->get();

        if ($products->isEmpty() || ! $demoFarmer) {
            return;
        }

        // 1. Seed Delivered Order & Review for Demo Farmer
        $prod1 = $products->first();
        $prod2 = $products->skip(1)->first();

        if ($prod1 && $prod2) {
            $demoDeliveredOrder = Order::create([
                'user_id' => $demoFarmer->id,
                'status' => 'delivered',
                'channel' => 'storefront',
                'payment_mode' => 'cash_on_delivery',
                'notes' => 'Pondside delivery completed successfully.',
                'total' => ($prod1->price * 2) + ($prod2->price * 1),
                'created_at' => now()->subDays(7),
                'updated_at' => now()->subDays(4),
            ]);

            $var1 = $prod1->variants->first();
            $var2 = $prod2->variants->first();

            OrderItem::create([
                'order_id' => $demoDeliveredOrder->id,
                'product_id' => $prod1->id,
                'product_variant_id' => $var1?->id,
                'quantity' => 2,
                'price_at_purchase' => $var1 ? $var1->price : $prod1->price,
            ]);

            OrderItem::create([
                'order_id' => $demoDeliveredOrder->id,
                'product_id' => $prod2->id,
                'product_variant_id' => $var2?->id,
                'quantity' => 1,
                'price_at_purchase' => $var2 ? $var2->price : $prod2->price,
            ]);

            // Review for prod1 by demoFarmer (leaving prod2 unreviewed for testing "Rate this product" prompt)
            ProductReview::firstOrCreate(
                [
                    'product_id' => $prod1->id,
                    'user_id' => $demoFarmer->id,
                    'order_id' => $demoDeliveredOrder->id,
                ],
                [
                    'rating' => 5,
                    'comment' => 'Outstanding grower feed! High protein content ensured consistent appetite and fast weight gain in our shrimp pond.',
                    'created_at' => now()->subDays(3),
                ]
            );
        }

        // 2. Seed Realistic Reviews from Other Farmers across multiple products
        $reviewPool = [
            [
                'rating' => 5,
                'comment' => 'Excellent quality pellets with almost zero dust. Sinks smoothly and doesn\'t cloud the pond bottom.',
            ],
            [
                'rating' => 4,
                'comment' => 'Very satisfied with the batch. Delivered in sturdy moisture-resistant packaging right to our farm gate.',
            ],
            [
                'rating' => 5,
                'comment' => 'Noticed marked improvement in survival rates and juvenile vigor within 2 weeks of use.',
            ],
            [
                'rating' => 4,
                'comment' => 'Reliable aquaculture input with authentic batch certificates. Good customer support from FarmLink team.',
            ],
            [
                'rating' => 5,
                'comment' => 'Highly recommended for commercial pond managers looking for steady FCR and pond water stability.',
            ],
        ];

        foreach ($otherFarmers as $idx => $farmer) {
            // Pick a product to review
            $targetProduct = $products->get($idx % $products->count());
            if (! $targetProduct) {
                continue;
            }

            $order = Order::create([
                'user_id' => $farmer->id,
                'status' => 'delivered',
                'channel' => 'storefront',
                'payment_mode' => 'cash_on_delivery',
                'total' => $targetProduct->price,
                'created_at' => now()->subDays(10 + $idx),
                'updated_at' => now()->subDays(6 + $idx),
            ]);

            $var = $targetProduct->variants->first();

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $targetProduct->id,
                'product_variant_id' => $var?->id,
                'quantity' => 1,
                'price_at_purchase' => $var ? $var->price : $targetProduct->price,
            ]);

            $template = $reviewPool[$idx % count($reviewPool)];

            ProductReview::firstOrCreate(
                [
                    'product_id' => $targetProduct->id,
                    'user_id' => $farmer->id,
                    'order_id' => $order->id,
                ],
                [
                    'rating' => $template['rating'],
                    'comment' => $template['comment'],
                    'created_at' => now()->subDays(5 + $idx),
                ]
            );
        }

        // Also add an additional review for Product 1 to give it multiple reviews
        if ($prod1 && $otherFarmers->count() >= 2) {
            $secondFarmer = $otherFarmers->skip(1)->first();
            if ($secondFarmer) {
                $extraOrder = Order::create([
                    'user_id' => $secondFarmer->id,
                    'status' => 'delivered',
                    'channel' => 'storefront',
                    'payment_mode' => 'cash_on_delivery',
                    'total' => $prod1->price * 3,
                    'created_at' => now()->subDays(12),
                    'updated_at' => now()->subDays(8),
                ]);

                $v = $prod1->variants->first();

                OrderItem::create([
                    'order_id' => $extraOrder->id,
                    'product_id' => $prod1->id,
                    'product_variant_id' => $v?->id,
                    'quantity' => 3,
                    'price_at_purchase' => $v ? $v->price : $prod1->price,
                ]);

                ProductReview::firstOrCreate(
                    [
                        'product_id' => $prod1->id,
                        'user_id' => $secondFarmer->id,
                        'order_id' => $extraOrder->id,
                    ],
                    [
                        'rating' => 4,
                        'comment' => 'Consistently high quality feed. The shrimp digested it thoroughly with minimal water pollution.',
                        'created_at' => now()->subDays(7),
                    ]
                );
            }
        }
    }
}
