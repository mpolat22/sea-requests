<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfq_supplier_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfq_id')->constrained('rfqs')->cascadeOnDelete();
            $table->foreignId('supplier_service_listing_id')->nullable()->constrained('supplier_service_listings')->nullOnDelete();
            $table->foreignId('seller_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('company_name');
            $table->string('category_name')->nullable();
            $table->string('subcategory_name')->nullable();
            $table->string('country_name')->nullable();
            $table->string('port_name')->nullable();
            $table->timestamps();

            $table->unique(['rfq_id', 'supplier_service_listing_id'], 'rfq_supplier_listing_unique');
            $table->index(['rfq_id', 'seller_id'], 'rfq_supplier_seller_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfq_supplier_recipients');
    }
};
