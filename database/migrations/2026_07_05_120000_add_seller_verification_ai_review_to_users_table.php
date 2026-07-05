<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('seller_verification_ai_review')->nullable()->after('seller_verification_submitted_at');
            $table->timestamp('seller_verification_ai_reviewed_at')->nullable()->after('seller_verification_ai_review');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'seller_verification_ai_review',
                'seller_verification_ai_reviewed_at',
            ]);
        });
    }
};
