<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('supplier_service_listings')
            ->where(function ($query) {
                $query->where('subcategory_name', 'Food Processors')
                    ->orWhere('subcategory_slug', 'food-processors');
            })
            ->update([
                'subcategory_name' => 'Food Provision',
                'subcategory_slug' => 'food-provision',
                'search_text' => DB::raw(
                    "REPLACE(REPLACE(search_text, 'Food Processors', 'Food Provision'), 'food processors', 'food provision')"
                ),
                'updated_at' => now(),
            ]);

        DB::table('rfq_supplier_recipients')
            ->where('subcategory_name', 'Food Processors')
            ->update([
                'subcategory_name' => 'Food Provision',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('supplier_service_listings')
            ->where(function ($query) {
                $query->where('subcategory_name', 'Food Provision')
                    ->orWhere('subcategory_slug', 'food-provision');
            })
            ->update([
                'subcategory_name' => 'Food Processors',
                'subcategory_slug' => 'food-processors',
                'search_text' => DB::raw(
                    "REPLACE(REPLACE(search_text, 'Food Provision', 'Food Processors'), 'food provision', 'food processors')"
                ),
                'updated_at' => now(),
            ]);

        DB::table('rfq_supplier_recipients')
            ->where('subcategory_name', 'Food Provision')
            ->update([
                'subcategory_name' => 'Food Processors',
                'updated_at' => now(),
            ]);
    }
};
