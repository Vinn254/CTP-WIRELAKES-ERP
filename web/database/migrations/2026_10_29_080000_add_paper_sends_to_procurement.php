<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A purchase order and a quotation are paper documents as much as rows: an order is handed to a supplier physically
 * or emailed to them, and a quotation is printed for the file. Both keep the address the copy actually went to and
 * when, so the paper trail shows what the supplier was given.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['purchase_orders', 'purchase_quotations'] as $table) {
            foreach (['emailed_at' => 'timestamp', 'sent_to' => 'string', 'sent_note' => 'string'] as $column => $type) {
                if (Schema::hasColumn($table, $column)) {
                    continue;
                }

                Schema::table($table, function (Blueprint $blueprint) use ($column, $type) {
                    if ($type === 'timestamp') {
                        $blueprint->timestamp($column)->nullable();
                    } else {
                        $blueprint->string($column, $column === 'sent_note' ? 1000 : 190)->nullable();
                    }
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['purchase_orders', 'purchase_quotations'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn(['emailed_at', 'sent_to', 'sent_note']);
            });
        }
    }
};
