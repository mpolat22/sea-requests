<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outreach_sender_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('audience')->default('supplier')->index();
            $table->string('name');
            $table->string('from_name');
            $table->string('from_email');
            $table->string('reply_to_email')->nullable();
            $table->string('smtp_host');
            $table->unsignedSmallInteger('smtp_port')->default(587);
            $table->string('smtp_encryption')->nullable();
            $table->string('smtp_username');
            $table->text('smtp_password');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('outreach_send_logs', function (Blueprint $table) {
            $table->foreignId('sender_account_id')
                ->nullable()
                ->after('template_id')
                ->constrained('outreach_sender_accounts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('outreach_send_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sender_account_id');
        });

        Schema::dropIfExists('outreach_sender_accounts');
    }
};
