<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfq_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->string('request_type', 40)->nullable();
            $table->string('currency', 12);
            $table->string('status', 20)->default('draft');
            $table->boolean('including_tax')->default(true);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->boolean('including_packing')->default(true);
            $table->decimal('packing_cost', 15, 2)->default(0);
            $table->boolean('including_freight')->default(true);
            $table->decimal('freight_cost', 15, 2)->default(0);
            $table->decimal('total_offer_amount', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->string('delivery_terms', 40)->nullable();
            $table->string('other_delivery_terms')->nullable();
            $table->decimal('payment_order_confirmation', 7, 2)->nullable();
            $table->decimal('payment_before_shipment', 7, 2)->nullable();
            $table->unsignedInteger('payment_invoice_days')->nullable();
            $table->string('other_payment_terms')->nullable();
            $table->text('general_note')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['rfq_id', 'seller_id']);
            $table->index(['seller_id', 'status']);
            $table->index(['rfq_id', 'status']);
        });

        Schema::create('offer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rfq_item_id')->constrained('rfq_items')->cascadeOnDelete();
            $table->unsignedInteger('line_no')->default(0);
            $table->decimal('offer_qty', 15, 2);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('line_total', 15, 2);
            $table->string('delivery_time')->nullable();
            $table->string('quality', 40)->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('remarks')->nullable();
            $table->timestamps();

            $table->unique(['offer_id', 'rfq_item_id']);
            $table->index(['offer_id', 'line_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_items');
        Schema::dropIfExists('offers');
    }
};
