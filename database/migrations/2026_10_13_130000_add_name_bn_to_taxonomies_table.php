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
        Schema::table('taxonomies', function (Blueprint $table) {
            $table->string('name_bn', 150)->nullable()->after('name');
        });

        // Seed / backfill standard Bengali names for canonical taxonomy options
        $bnTranslations = [
            // 1. Farm Type
            'farm_type' => [
                'own' => 'নিজস্ব জমি / ঘের',
                'leased' => 'ইজারা / লিজকৃত ঘের',
                'family' => 'পারিবারিক উত্তরাধিকার জমি',
                'partnership' => 'যৌথ অংশীদারিত্বের ঘের',
                'other' => 'অন্যান্য ধরন',
            ],
            // 2. Culture Type
            'culture_type' => [
                'bagda' => 'বাগদা চিংড়ি (Black Tiger)',
                'golda' => 'গলদা চিংড়ি (Freshwater Prawn)',
                'tilapia' => 'তেলাপিয়া মাছ',
                'pangas' => 'পাঙ্গাস মাছ',
                'carp' => 'কার্প জাতীয় মাছ (রুই / কাতলা / মৃগেল)',
                'other' => 'অন্যান্য জলজ প্রজাতি',
            ],
            // 3. Farming System
            'farming_system' => [
                'extensive' => 'সনাতন পদ্ধতি (Extensive)',
                'improved_extensive' => 'উন্নত সনাতন পদ্ধতি',
                'semi_intensive' => 'আধা-নিবিড় পদ্ধতি (Semi-intensive)',
                'intensive' => 'নিবিড় চাষ পদ্ধতি (Intensive)',
                'other' => 'অন্যান্য পদ্ধতি (Biofloc / RAS)',
            ],
            // 4. Main Water Source
            'water_source' => [
                'river' => 'প্রাকৃতিক নদী',
                'canal' => 'খাল / ক্যানেল',
                'deep_tubewell' => 'গভীর নলকূপ',
                'rainfed' => 'বৃষ্টির পানি নির্ভর জলাশয়',
                'tidal_creek' => 'উপকূলীয় জোয়ারের খাঁড়ি / মোহনা',
                'other' => 'অন্যান্য পানির উৎস',
            ],
            // 5. Available Facilities
            'facility' => [
                'electric' => 'বিদ্যুৎ সংযোগ (পল্লী বিদ্যুৎ)',
                'generator' => 'জেনারেটর (ব্যাকআপ বিদ্যুৎ)',
                'solar' => 'সোলার বিদ্যুৎ ব্যবস্থা',
                'pump' => 'পানি পাম্প (সেচ ও নিষ্কাশন)',
                'aerator' => 'পেডেলহুইল এয়ারেটর',
                'feeding_equip' => 'খাদ্য প্রয়োগ সরঞ্জাম / অটো ফিডার',
                'storage' => 'ফিড ও সামগ্রী গুদামঘর',
                'net_harvest' => 'মাছ ধরার জাল ও আহরণ সরঞ্জাম',
                'fencing' => 'বায়োসিকিউরিটি বেড়া ও জাল বেষ্টনী',
            ],
            // 6. Product Category
            'product_category' => [
                'feed' => 'মাছের খাদ্য ও পুষ্টি (ফিড)',
                'medicine' => 'ভেট ওষুধ ও স্বাস্থ্য সামগ্রী',
                'chemicals' => 'পানি ও মাটি শোধন কেমিক্যাল',
                'equipment' => 'খামার যন্ত্রপাতি ও টেস্ট কিট',
                'probiotics' => 'প্রোবায়োটিক ও জৈব প্রতিকারক',
            ],
            // 7. Specialty Tags
            'specialty_tag' => [
                'water_quality' => 'পানির মান ও পরিবেশ ব্যবস্থাপনা',
                'disease_pathology' => 'রোগ নির্ণয় ও প্যাথলজি',
                'biosecurity' => 'বায়োসিকিউরিটি ও খামার জীবাণুমুক্তকরণ',
                'nutrition' => 'পুষ্টি ও খাদ্য ব্যবস্থাপনা (FCR)',
                'nursery_prep' => 'হ্যাচারি ও নার্সারি ব্যবস্থাপনা',
            ],
        ];

        foreach ($bnTranslations as $type => $slugMap) {
            foreach ($slugMap as $slug => $bnName) {
                DB::table('taxonomies')
                    ->where('type', $type)
                    ->where('slug', $slug)
                    ->update(['name_bn' => $bnName]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('taxonomies', function (Blueprint $table) {
            $table->dropColumn('name_bn');
        });
    }
};
