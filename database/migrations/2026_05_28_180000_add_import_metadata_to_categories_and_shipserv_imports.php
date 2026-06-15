<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('source')->nullable()->after('sort_order');
            $table->unsignedBigInteger('source_external_id')->nullable()->after('source');
            $table->string('source_url')->nullable()->after('source_external_id');
            $table->json('metadata')->nullable()->after('source_url');

            $table->index(['source', 'source_external_id']);
        });

        Schema::table('subcategories', function (Blueprint $table) {
            $table->string('source')->nullable()->after('sort_order');
            $table->unsignedBigInteger('source_external_id')->nullable()->after('source');
            $table->string('source_url')->nullable()->after('source_external_id');
            $table->json('metadata')->nullable()->after('source_url');

            $table->index(['source', 'source_external_id']);
        });

        Schema::table('shipserv_category_imports', function (Blueprint $table) {
            $table->string('normalized_name')->nullable()->after('name')->index();
            $table->string('normalized_slug')->nullable()->after('slug')->index();
            $table->string('content_type')->nullable()->after('letter')->index();
            $table->string('suggestion_type')->nullable()->after('content_type')->index();
            $table->string('suggested_parent_name')->nullable()->after('suggestion_type')->index();
            $table->string('suggested_parent_slug')->nullable()->after('suggested_parent_name')->index();
            $table->string('suggested_parent_source')->nullable()->after('suggested_parent_slug');
            $table->unsignedTinyInteger('suggestion_confidence')->nullable()->after('suggested_parent_source')->index();
            $table->string('suggestion_rule')->nullable()->after('suggestion_confidence')->index();
            $table->string('publish_status')->default('pending')->after('mapping_status')->index();
        });
    }

    public function down(): void
    {
        Schema::table('shipserv_category_imports', function (Blueprint $table) {
            $table->dropIndex(['normalized_name']);
            $table->dropIndex(['normalized_slug']);
            $table->dropIndex(['content_type']);
            $table->dropIndex(['suggestion_type']);
            $table->dropIndex(['suggested_parent_name']);
            $table->dropIndex(['suggested_parent_slug']);
            $table->dropIndex(['suggestion_confidence']);
            $table->dropIndex(['suggestion_rule']);
            $table->dropIndex(['publish_status']);
            $table->dropColumn([
                'normalized_name',
                'normalized_slug',
                'content_type',
                'suggestion_type',
                'suggested_parent_name',
                'suggested_parent_slug',
                'suggested_parent_source',
                'suggestion_confidence',
                'suggestion_rule',
                'publish_status',
            ]);
        });

        Schema::table('subcategories', function (Blueprint $table) {
            $table->dropIndex(['source', 'source_external_id']);
            $table->dropColumn(['source', 'source_external_id', 'source_url', 'metadata']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['source', 'source_external_id']);
            $table->dropColumn(['source', 'source_external_id', 'source_url', 'metadata']);
        });
    }
};
