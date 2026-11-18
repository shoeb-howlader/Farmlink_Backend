<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $services = [
            [
                'name' => 'Water Quality Test (Lab / Field)',
                'name_bn' => 'পুকুর ও ঘেরের পানি পরীক্ষা (ল্যাব টেস্ট)',
                'category' => 'Services',
                'category_bn' => 'ল্যাব টেস্ট ও সেবা',
                'price' => 100.00,
                'stock' => 999999,
                'is_active' => true,
                'short_description' => 'Comprehensive water test: Dissolved Oxygen, pH, Ammonia (TAN), Salinity & Alkalinity',
                'short_description_bn' => 'পানি পরীক্ষা: অক্সিজেন (DO), পিএইচ (pH), অ্যামোনিয়া, লবণাক্ততা ও ক্ষারত্ব',
                'description' => 'In-store diagnostic laboratory testing for aquaculture ponds and agricultural water sources.',
                'description_bn' => 'খামারের পানির গুণাগুণ নিশ্চিতকরণে ডিজিটাল ও রিএজেন্ট ভিত্তিক ইন-স্টোর ল্যাব পরীক্ষা।',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Soil Quality & Parameter Test',
                'name_bn' => 'মাটির গুণাগুণ ও পুষ্টি পরীক্ষা',
                'category' => 'Services',
                'category_bn' => 'ল্যাব টেস্ট ও সেবা',
                'price' => 200.00,
                'stock' => 999999,
                'is_active' => true,
                'short_description' => 'Soil pH, organic matter, and bottom sludge analysis',
                'short_description_bn' => 'মাটির পিএইচ, জৈব পদার্থ এবং তলার বিষাক্ত কাদা পরীক্ষা',
                'description' => 'Comprehensive soil testing for pond bottoms and crop fields.',
                'description_bn' => 'পুকুরের তলার মাটি এবং চাষের জমির উর্বরতা ও অম্লত্ব নির্ণয় পরীক্ষা।',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'In-Store Veterinary / Agri Consultancy Fee',
                'name_bn' => 'ইন-স্টোর পরামর্শ ও প্রেসক্রিপশন ফি',
                'category' => 'Services',
                'category_bn' => 'ল্যাব টেস্ট ও সেবা',
                'price' => 150.00,
                'stock' => 999999,
                'is_active' => true,
                'short_description' => 'Specialist consultancy and treatment plan at physical store',
                'short_description_bn' => 'ডিপো বা দোকানে বিশেষজ্ঞ পরামর্শ, রোগ নির্ণয় এবং প্রেসক্রিপশন প্রদান',
                'description' => 'Professional advisory session provided by FarmLink veterinary doctor or aquaculture consultant.',
                'description_bn' => 'ফার্মলিংক বিশেষজ্ঞ কনসালট্যান্ট বা ভেট চিকিৎসকের কাছ থেকে সরাসরি রোগ নির্ণয় ও চিকিৎসা পরামর্শ।',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($services as $service) {
            $exists = DB::table('products')->where('name', $service['name'])->exists();
            if (! $exists) {
                DB::table('products')->insert($service);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('products')->whereIn('name', [
            'Water Quality Test (Lab / Field)',
            'Soil Quality & Parameter Test',
            'In-Store Veterinary / Agri Consultancy Fee',
        ])->delete();
    }
};
