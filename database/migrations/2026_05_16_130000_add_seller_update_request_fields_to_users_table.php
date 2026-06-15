<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('seller_update_request_status')->nullable()->after('seller_rejected_at');
            $table->json('seller_update_request_payload')->nullable()->after('seller_update_request_status');
            $table->json('seller_update_request_diff')->nullable()->after('seller_update_request_payload');
            $table->timestamp('seller_update_requested_at')->nullable()->after('seller_update_request_diff');
            $table->string('seller_update_rejection_reason')->nullable()->after('seller_update_requested_at');
            $table->text('seller_update_rejection_note')->nullable()->after('seller_update_rejection_reason');
            $table->json('seller_update_rejection_fields')->nullable()->after('seller_update_rejection_note');
            $table->timestamp('seller_update_rejected_at')->nullable()->after('seller_update_rejection_fields');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'seller_update_request_status',
                'seller_update_request_payload',
                'seller_update_request_diff',
                'seller_update_requested_at',
                'seller_update_rejection_reason',
                'seller_update_rejection_note',
                'seller_update_rejection_fields',
                'seller_update_rejected_at',
            ]);
        });
    }
};
