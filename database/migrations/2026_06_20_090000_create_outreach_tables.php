<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outreach_segments', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('audience')->default('supplier');
            $table->string('region_key')->nullable()->index();
            $table->unsignedTinyInteger('recommended_weekday')->nullable();
            $table->time('recommended_start_time')->nullable();
            $table->time('recommended_end_time')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('outreach_templates', function (Blueprint $table) {
            $table->id();
            $table->string('audience')->default('supplier')->index();
            $table->string('name');
            $table->string('subject');
            $table->longText('body_text');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('outreach_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('audience')->default('supplier')->index();
            $table->foreignId('primary_segment_id')->nullable()->constrained('outreach_segments')->nullOnDelete();
            $table->string('organization_name')->nullable();
            $table->string('source_name')->nullable();
            $table->string('status')->default('active')->index();
            $table->unsignedInteger('next_template_step')->default(1);
            $table->unsignedInteger('sent_count')->default(0);
            $table->foreignId('last_template_id')->nullable()->constrained('outreach_templates')->nullOnDelete();
            $table->timestamp('last_sent_at')->nullable()->index();
            $table->string('last_result')->nullable();
            $table->text('notes')->nullable();
            $table->json('source_payload')->nullable();
            $table->timestamps();
        });

        Schema::create('outreach_contact_segment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outreach_contact_id')->constrained('outreach_contacts')->cascadeOnDelete();
            $table->foreignId('outreach_segment_id')->constrained('outreach_segments')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['outreach_contact_id', 'outreach_segment_id'], 'outreach_contact_segment_unique');
        });

        Schema::create('outreach_import_runs', function (Blueprint $table) {
            $table->id();
            $table->string('audience')->default('supplier')->index();
            $table->string('file_name');
            $table->string('stored_path');
            $table->string('status')->default('queued')->index();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('new_contacts_count')->default(0);
            $table->unsignedInteger('updated_contacts_count')->default(0);
            $table->unsignedInteger('duplicate_emails_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->text('message')->nullable();
            $table->json('summary')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('outreach_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('segment_id')->constrained('outreach_segments')->cascadeOnDelete();
            $table->string('audience')->default('supplier')->index();
            $table->string('recurrence')->default('biweekly');
            $table->date('starts_on');
            $table->unsignedTinyInteger('weekday');
            $table->time('suggested_start_time')->nullable();
            $table->time('suggested_end_time')->nullable();
            $table->boolean('uses_recommended_window')->default(true);
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('send_interval_minutes')->default(1);
            $table->boolean('is_active')->default(true);
            $table->json('template_rotation')->nullable();
            $table->timestamp('last_dispatched_at')->nullable();
            $table->string('last_cycle_key')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->unique('segment_id');
        });

        Schema::create('outreach_send_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('outreach_contacts')->cascadeOnDelete();
            $table->foreignId('segment_id')->constrained('outreach_segments')->cascadeOnDelete();
            $table->foreignId('schedule_id')->constrained('outreach_schedules')->cascadeOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('outreach_templates')->nullOnDelete();
            $table->string('cycle_key')->index();
            $table->string('recipient_email')->index();
            $table->string('recipient_organization')->nullable();
            $table->string('sender_email')->nullable();
            $table->string('status')->default('queued')->index();
            $table->string('subject')->nullable();
            $table->longText('body_text')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamps();
            $table->unique(['contact_id', 'schedule_id', 'cycle_key'], 'outreach_send_logs_unique_cycle');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outreach_send_logs');
        Schema::dropIfExists('outreach_schedules');
        Schema::dropIfExists('outreach_import_runs');
        Schema::dropIfExists('outreach_templates');
        Schema::dropIfExists('outreach_contact_segment');
        Schema::dropIfExists('outreach_contacts');
        Schema::dropIfExists('outreach_segments');
    }
};
