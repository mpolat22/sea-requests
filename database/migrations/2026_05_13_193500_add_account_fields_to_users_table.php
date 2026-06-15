<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('buyer')->after('email');
            $table->string('company_name')->nullable()->after('name');
            $table->string('phone')->nullable()->after('company_name');
            $table->string('approval_status')->default('pending')->after('email_verified_at');
            $table->timestamp('approved_at')->nullable()->after('approval_status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'role',
                'company_name',
                'phone',
                'approval_status',
                'approved_at',
            ]);
        });
    }
};
