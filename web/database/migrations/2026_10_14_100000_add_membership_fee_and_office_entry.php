<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module 2 / Module 5 — the one-time membership fee is KSh 1,000 for a CTP and for an E-Fundi,
 * a CTP sponsored by a partner pays nothing, and the office can open a CTP application itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->feeSetting('membership_fee_ctp', '1000');
        $this->feeSetting('membership_fee_fundi', '1000');

        // The public fee page reads the CMS copy, so the number there moves with the fee.
        $field = DB::table('content_fields')->where('key', 'fee_ctp')->where('type', 'price')->first();
        if ($field) {
            DB::table('content_values')->where('field_id', $field->id)->update(['text_value' => '1000']);
        }

        Schema::table('ctp_registrations', function (Blueprint $table) {
            $table->foreignId('sponsor_id')->nullable()->after('fee_paid_at')
                ->constrained('partners')->nullOnDelete();
            $table->string('source', 12)->default('self')->after('sponsor_id');
            $table->foreignId('added_by')->nullable()->after('source')->constrained('users')->nullOnDelete();
        });

        Schema::table('fundi_registrations', function (Blueprint $table) {
            $table->enum('status', ['pending_payment', 'pending_review', 'active', 'rejected'])
                ->default('pending_payment')->change();

            $table->string('fee_status', 20)->default('awaiting_payment')->after('status');
            $table->decimal('fee_amount', 10, 2)->nullable()->after('fee_status');
            $table->string('fee_method', 20)->nullable()->after('fee_amount');
            $table->string('fee_reference', 60)->nullable()->after('fee_method');
            $table->timestamp('fee_paid_at')->nullable()->after('fee_reference');
        });

        // E-Fundis already inside the programme are not debtors — the fee was settled before it was tracked.
        DB::table('fundi_registrations')->whereNotNull('ef_code')->update(['fee_status' => 'waived']);
        // Applications still waiting for review now pass the fee step first, like every other applicant.
        DB::table('fundi_registrations')->where('status', 'pending_review')
            ->update(['status' => 'pending_payment']);

        // A CTP with a sponsoring partner has no fee to pay and goes straight to review.
        DB::table('ctp_registrations')->where('status', 'pending_payment')
            ->whereNotNull('sponsor_id')
            ->update(['fee_status' => 'sponsored']);
    }

    public function down(): void
    {
        DB::table('ctp_registrations')->where('fee_status', 'sponsored')->update(['fee_status' => 'awaiting_payment']);
        DB::table('fundi_registrations')->where('status', 'pending_payment')->update(['status' => 'pending_review']);

        Schema::table('ctp_registrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sponsor_id');
            $table->dropConstrainedForeignId('added_by');
            $table->dropColumn('source');
        });

        Schema::table('fundi_registrations', function (Blueprint $table) {
            $table->dropColumn(['fee_status', 'fee_amount', 'fee_method', 'fee_reference', 'fee_paid_at']);
            $table->enum('status', ['pending_review', 'active', 'rejected'])->default('pending_review')->change();
        });

        $this->feeSetting('membership_fee_ctp', '500');
        $field = DB::table('content_fields')->where('key', 'fee_ctp')->where('type', 'price')->first();
        if ($field) {
            DB::table('content_values')->where('field_id', $field->id)->update(['text_value' => '500']);
        }
    }

    private function feeSetting(string $key, string $value): void
    {
        DB::table('settings')->where('key', $key)->exists()
            ? DB::table('settings')->where('key', $key)->update(['value' => $value, 'updated_at' => now()])
            : DB::table('settings')->insert(['key' => $key, 'value' => $value, 'created_at' => now(), 'updated_at' => now()]);
    }
};
