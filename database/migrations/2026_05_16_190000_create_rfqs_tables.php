<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfqs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->string('reference_no')->index();
            $table->string('company_name');
            $table->string('ship_name');
            $table->string('country_name');
            $table->string('port_name');
            $table->date('delivery_date');
            $table->date('due_date')->nullable();
            $table->string('currency', 8);
            $table->string('urgency', 24)->default('normal')->index();
            $table->string('status', 24)->default('draft')->index();
            $table->text('general_notes')->nullable();
            $table->unsignedInteger('items_count')->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['buyer_id', 'status'], 'rfqs_buyer_status_idx');
        });

        Schema::create('rfq_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfq_id')->constrained('rfqs')->cascadeOnDelete();
            $table->unsignedInteger('line_no');
            $table->string('product_name');
            $table->string('part_no')->nullable();
            $table->decimal('quantity', 14, 2);
            $table->string('unit', 24);
            $table->string('brand')->nullable();
            $table->string('quality', 24);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['rfq_id', 'line_no'], 'rfq_items_line_idx');
        });

        Schema::create('rfq_item_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfq_item_id')->constrained('rfq_items')->cascadeOnDelete();
            $table->string('disk')->default('public');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfq_item_attachments');
        Schema::dropIfExists('rfq_items');
        Schema::dropIfExists('rfqs');
    }
};
