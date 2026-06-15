<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rfqs', function (Blueprint $table) {
            $table->string('request_type', 32)->default('spare_parts')->after('ship_name');
            $table->string('service_title')->nullable()->after('general_notes');
            $table->text('service_description')->nullable()->after('service_title');
        });

        Schema::create('rfq_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfq_id')->constrained('rfqs')->cascadeOnDelete();
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
        Schema::dropIfExists('rfq_attachments');

        Schema::table('rfqs', function (Blueprint $table) {
            $table->dropColumn([
                'request_type',
                'service_title',
                'service_description',
            ]);
        });
    }
};
