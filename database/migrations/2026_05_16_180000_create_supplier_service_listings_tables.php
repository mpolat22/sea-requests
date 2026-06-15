<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_service_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->string('listing_key')->unique();
            $table->string('company_name')->nullable()->index();
            $table->string('contact_name')->nullable();
            $table->string('country')->nullable()->index();
            $table->text('summary')->nullable();
            $table->string('logo_path')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('category_name')->nullable();
            $table->string('category_slug')->nullable()->index();
            $table->foreignId('subcategory_id')->nullable()->constrained('subcategories')->nullOnDelete();
            $table->string('subcategory_name')->nullable();
            $table->string('subcategory_slug')->nullable()->index();
            $table->string('vendor_slug')->nullable()->index();
            $table->text('search_text')->nullable();
            $table->boolean('is_visible')->default(true)->index();
            $table->timestamps();

            $table->index(['is_visible', 'category_id'], 'ssl_visible_category_idx');
            $table->index(['is_visible', 'subcategory_id'], 'ssl_visible_subcategory_idx');
            $table->index(['seller_id', 'is_visible'], 'ssl_seller_visible_idx');
        });

        Schema::create('supplier_service_listing_ports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_service_listing_id')
                ->constrained('supplier_service_listings', indexName: 'ssl_ports_listing_fk')
                ->cascadeOnDelete();
            $table->string('country_code', 8)->nullable()->index();
            $table->string('country_name')->nullable()->index();
            $table->string('port_name')->nullable()->index();
            $table->string('unlocode', 16)->nullable()->index();

            $table->index(['supplier_service_listing_id', 'country_code'], 'ssl_ports_listing_country_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_service_listing_ports');
        Schema::dropIfExists('supplier_service_listings');
    }
};
