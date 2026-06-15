<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('source')->nullable();
            $table->unsignedBigInteger('source_external_id')->nullable();
            $table->string('source_url')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['source', 'source_external_id']);
        });

        Schema::create('shipserv_category_imports', function (Blueprint $table) {
            $table->id();
            $table->uuid('import_batch')->nullable()->index();
            $table->unsignedBigInteger('shipserv_external_id')->nullable()->index();
            $table->string('name');
            $table->string('slug');
            $table->string('letter', 8)->nullable()->index();
            $table->string('source_url')->unique();
            $table->string('source_path')->nullable();
            $table->string('discovered_on')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->string('mapping_status')->default('pending')->index();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subcategory_id')->nullable()->constrained()->nullOnDelete();
            $table->text('mapping_notes')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamp('imported_at')->nullable()->index();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('shipserv_brand_imports', function (Blueprint $table) {
            $table->id();
            $table->uuid('import_batch')->nullable()->index();
            $table->unsignedBigInteger('shipserv_external_id')->nullable()->index();
            $table->string('name');
            $table->string('slug');
            $table->string('letter', 8)->nullable()->index();
            $table->string('source_url')->unique();
            $table->string('source_path')->nullable();
            $table->string('discovered_on')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->string('mapping_status')->default('pending')->index();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->text('mapping_notes')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamp('imported_at')->nullable()->index();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipserv_brand_imports');
        Schema::dropIfExists('shipserv_category_imports');
        Schema::dropIfExists('brands');
    }
};
