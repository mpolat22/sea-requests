<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->string('invoice_number', 120)->nullable()->after('order_workflow_status');
            $table->date('invoice_date')->nullable()->after('invoice_number');
            $table->decimal('invoice_amount', 12, 2)->nullable()->after('invoice_date');
            $table->text('invoice_notes')->nullable()->after('invoice_amount');
            $table->string('invoice_document_disk', 40)->nullable()->after('invoice_notes');
            $table->string('invoice_document_path')->nullable()->after('invoice_document_disk');
            $table->string('invoice_document_name')->nullable()->after('invoice_document_path');
            $table->string('invoice_document_mime_type', 160)->nullable()->after('invoice_document_name');
            $table->unsignedBigInteger('invoice_document_size')->nullable()->after('invoice_document_mime_type');
        });
    }

    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn([
                'invoice_number',
                'invoice_date',
                'invoice_amount',
                'invoice_notes',
                'invoice_document_disk',
                'invoice_document_path',
                'invoice_document_name',
                'invoice_document_mime_type',
                'invoice_document_size',
            ]);
        });
    }
};
