<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Not every purchase starts inside the system: the office buys something at the counter and brings the receipt in
 * afterwards. Such a row is written straight onto the register as already paid, marked as recorded by hand and
 * carrying the supplier's name as typed, so it never looks like a request that walked the eight steps.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->timestamp('recorded_by_hand_on')->nullable()->after('rejection_reason');
            $table->string('supplier_label', 190)->nullable()->after('recorded_by_hand_on');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropColumn(['recorded_by_hand_on', 'supplier_label']);
        });
    }
};
