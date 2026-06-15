<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offer_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->constrained('offers')->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('body')->nullable();
            $table->string('attachment_disk')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('attachment_mime_type')->nullable();
            $table->unsignedBigInteger('attachment_size')->nullable();
            $table->timestamps();

            $table->index(['offer_id', 'id'], 'offer_messages_offer_id_id_idx');
        });

        Schema::create('offer_message_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->constrained('offers')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('last_read_message_id')->nullable()->constrained('offer_messages')->nullOnDelete();
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();

            $table->unique(['offer_id', 'user_id'], 'offer_message_reads_offer_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_message_reads');
        Schema::dropIfExists('offer_messages');
    }
};
