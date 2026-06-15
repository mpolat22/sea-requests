<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->boolean('including_mobilization')->default(true)->after('freight_cost');
            $table->decimal('mobilization_cost', 15, 2)->default(0)->after('including_mobilization');
            $table->string('completion_time')->nullable()->after('grand_total');
            $table->string('offer_validity')->nullable()->after('completion_time');
            $table->text('service_clarification')->nullable()->after('other_payment_terms');
        });

        Schema::create('offer_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 40);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();

            $table->index(['offer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_attachments');

        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn([
                'including_mobilization',
                'mobilization_cost',
                'completion_time',
                'offer_validity',
                'service_clarification',
            ]);
        });
    }
};
