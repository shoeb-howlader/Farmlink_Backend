<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('name_bn')->nullable()->after('name');
            $table->string('category_bn')->nullable()->after('category');
            $table->text('short_description_bn')->nullable()->after('short_description');
            $table->text('description_bn')->nullable()->after('description');
            $table->text('usage_instructions_bn')->nullable()->after('usage_instructions');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->string('variant_label_bn')->nullable()->after('variant_label');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn(['variant_label_bn']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'name_bn',
                'category_bn',
                'short_description_bn',
                'description_bn',
                'usage_instructions_bn',
            ]);
        });
    }
};
