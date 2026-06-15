<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('seller_rejection_reason')->nullable()->after('seller_verification_submitted_at');
            $table->text('seller_rejection_note')->nullable()->after('seller_rejection_reason');
            $table->json('seller_rejection_fields')->nullable()->after('seller_rejection_note');
            $table->timestamp('seller_rejected_at')->nullable()->after('seller_rejection_fields');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'seller_rejection_reason',
                'seller_rejection_note',
                'seller_rejection_fields',
                'seller_rejected_at',
            ]);
        });
    }
};
