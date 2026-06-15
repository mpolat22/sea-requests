<?php

use App\Models\Offer;
use App\Models\OfferAward;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->string('order_workflow_status', 40)
                ->nullable()
                ->after('status');
        });

        $confirmedOfferIds = OfferAward::query()
            ->where('status', OfferAward::STATUS_CONFIRMED)
            ->pluck('offer_id')
            ->filter()
            ->unique()
            ->values();

        if ($confirmedOfferIds->isNotEmpty()) {
            DB::table('offers')
                ->whereIn('id', $confirmedOfferIds->all())
                ->whereNull('order_workflow_status')
                ->update([
                    'order_workflow_status' => Offer::ORDER_STATUS_ORDER_INFORMATION_PENDING,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn('order_workflow_status');
        });
    }
};
