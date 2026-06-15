<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offer_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->constrained()->cascadeOnDelete();
            $table->string('currency', 10)->default('USD');
            $table->string('invoice_number', 120)->nullable();
            $table->date('invoice_date')->nullable();
            $table->decimal('invoice_amount', 12, 2)->nullable();
            $table->text('invoice_notes')->nullable();
            $table->string('invoice_document_disk', 40)->nullable();
            $table->string('invoice_document_path')->nullable();
            $table->string('invoice_document_name')->nullable();
            $table->string('invoice_document_mime_type', 160)->nullable();
            $table->unsignedBigInteger('invoice_document_size')->nullable();
            $table->date('payment_proof_date')->nullable();
            $table->string('payment_reference', 120)->nullable();
            $table->text('payment_notes')->nullable();
            $table->string('payment_proof_document_disk', 40)->nullable();
            $table->string('payment_proof_document_path')->nullable();
            $table->string('payment_proof_document_name')->nullable();
            $table->string('payment_proof_document_mime_type', 160)->nullable();
            $table->unsignedBigInteger('payment_proof_document_size')->nullable();
            $table->timestamp('payment_confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['offer_id', 'invoice_date']);
        });

        DB::table('offers')
            ->where(function ($query) {
                $query
                    ->whereNotNull('invoice_number')
                    ->orWhereNotNull('invoice_date')
                    ->orWhereNotNull('invoice_amount')
                    ->orWhereNotNull('invoice_notes')
                    ->orWhereNotNull('invoice_document_path');
            })
            ->orderBy('id')
            ->get()
            ->each(function ($offer) {
                DB::table('offer_invoices')->insert([
                    'offer_id' => $offer->id,
                    'currency' => $offer->currency ?: 'USD',
                    'invoice_number' => $offer->invoice_number,
                    'invoice_date' => $offer->invoice_date,
                    'invoice_amount' => $offer->invoice_amount,
                    'invoice_notes' => $offer->invoice_notes,
                    'invoice_document_disk' => $offer->invoice_document_disk,
                    'invoice_document_path' => $offer->invoice_document_path,
                    'invoice_document_name' => $offer->invoice_document_name,
                    'invoice_document_mime_type' => $offer->invoice_document_mime_type,
                    'invoice_document_size' => $offer->invoice_document_size,
                    'created_at' => $offer->updated_at ?? $offer->created_at ?? now(),
                    'updated_at' => $offer->updated_at ?? $offer->created_at ?? now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_invoices');
    }
};
