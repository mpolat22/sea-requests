<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('subcategories')
            ->where('name', 'Food Provision')
            ->update([
                'slug' => 'provisions-food-provision',
                'updated_at' => now(),
            ]);

        DB::table('shipserv_category_imports')
            ->where('name', 'Food Provision')
            ->update([
                'slug' => 'provisions-food-provision',
                'normalized_slug' => 'provisions-food-provision',
                'updated_at' => now(),
            ]);

        DB::table('supplier_service_listings')
            ->where('subcategory_name', 'Food Provision')
            ->update([
                'subcategory_slug' => 'provisions-food-provision',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('subcategories')
            ->where('name', 'Food Provision')
            ->update([
                'slug' => 'food-provision',
                'updated_at' => now(),
            ]);

        DB::table('shipserv_category_imports')
            ->where('name', 'Food Provision')
            ->update([
                'slug' => 'food-provision',
                'normalized_slug' => 'food-provision',
                'updated_at' => now(),
            ]);

        DB::table('supplier_service_listings')
            ->where('subcategory_name', 'Food Provision')
            ->update([
                'subcategory_slug' => 'food-provision',
                'updated_at' => now(),
            ]);
    }
};
