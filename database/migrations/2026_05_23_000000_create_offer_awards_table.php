<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offer_awards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfq_id')->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('offer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('offer_item_id')->nullable()->constrained('offer_items')->cascadeOnDelete();
            $table->foreignId('rfq_item_id')->nullable()->constrained('rfq_items')->cascadeOnDelete();
            $table->string('request_type', 40)->nullable();
            $table->string('status', 20)->default('draft');
            $table->decimal('awarded_quantity', 15, 2)->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['rfq_id', 'status']);
            $table->index(['offer_id', 'status']);
            $table->index(['rfq_item_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_awards');
    }
};
