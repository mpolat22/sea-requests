<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rfq_supplier_recipients', function (Blueprint $table) {
            $table->string('delivery_status', 24)->default('pending')->after('port_name');
            $table->timestamp('queued_at')->nullable()->after('delivery_status');
            $table->timestamp('delivered_at')->nullable()->after('queued_at');
            $table->timestamp('failed_at')->nullable()->after('delivered_at');
            $table->text('delivery_error')->nullable()->after('failed_at');
            $table->unsignedSmallInteger('delivery_attempts')->default(0)->after('delivery_error');
        });
    }

    public function down(): void
    {
        Schema::table('rfq_supplier_recipients', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_status',
                'queued_at',
                'delivered_at',
                'failed_at',
                'delivery_error',
                'delivery_attempts',
            ]);
        });
    }
};
