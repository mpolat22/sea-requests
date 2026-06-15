<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('rfq_id')->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('review_text');
            $table->text('seller_reply')->nullable();
            $table->timestamp('seller_replied_at')->nullable();
            $table->timestamps();

            $table->index(['seller_id', 'created_at']);
            $table->index(['buyer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_reviews');
    }
};
