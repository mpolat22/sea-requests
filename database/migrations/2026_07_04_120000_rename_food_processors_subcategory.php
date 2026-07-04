<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $provisionsCategoryIds = DB::table('categories')
            ->where('name', 'Provisions')
            ->pluck('id');

        if ($provisionsCategoryIds->isNotEmpty()) {
            DB::table('subcategories')
                ->whereIn('category_id', $provisionsCategoryIds)
                ->where('name', 'Food Processors')
                ->update([
                    'name' => 'Food Provision',
                    'slug' => 'food-provision',
                    'updated_at' => now(),
                ]);
        }

        DB::table('shipserv_category_imports')
            ->where('name', 'Food Processors')
            ->update([
                'name' => 'Food Provision',
                'slug' => 'food-provision',
                'normalized_name' => 'food provision',
                'normalized_slug' => 'food-provision',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        $provisionsCategoryIds = DB::table('categories')
            ->where('name', 'Provisions')
            ->pluck('id');

        if ($provisionsCategoryIds->isNotEmpty()) {
            DB::table('subcategories')
                ->whereIn('category_id', $provisionsCategoryIds)
                ->where('name', 'Food Provision')
                ->update([
                    'name' => 'Food Processors',
                    'slug' => 'food-processors',
                    'updated_at' => now(),
                ]);
        }

        DB::table('shipserv_category_imports')
            ->where('name', 'Food Provision')
            ->update([
                'name' => 'Food Processors',
                'slug' => 'food-processors',
                'normalized_name' => 'food processors',
                'normalized_slug' => 'food-processors',
                'updated_at' => now(),
            ]);
    }
};
