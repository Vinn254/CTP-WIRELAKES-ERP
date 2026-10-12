<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ctp_registrations', function (Blueprint $table) {
            $table->enum('status', [
                'pending_payment', 'pending_review', 'active', 'suspended',
                'graduated', 'deployed', 'rejected',
            ])->default('pending_payment')->change();

            $table->decimal('gps_lat', 10, 7)->nullable()->after('village');
            $table->decimal('gps_lng', 10, 7)->nullable()->after('gps_lat');

            $table->unsignedSmallInteger('year_left_school')->nullable()->after('education_level');
            $table->json('certificates_held')->nullable()->after('year_left_school');

            $table->string('fee_status', 20)->default('awaiting_payment')->after('status');
            $table->decimal('fee_amount', 10, 2)->nullable()->after('fee_status');
            $table->string('fee_method', 20)->nullable()->after('fee_amount');
            $table->string('fee_reference', 60)->nullable()->after('fee_method');
            $table->timestamp('fee_paid_at')->nullable()->after('fee_reference');

            $table->foreignId('referred_by')->nullable()->after('referral_code')
                ->constrained('ctp_registrations')->nullOnDelete();
            $table->timestamp('referred_at')->nullable()->after('referred_by');

            $table->json('service_counties')->nullable()->after('referred_at');
            $table->boolean('available_for_mobilisation')->default(true)->after('service_counties');
            $table->boolean('trainer_opt_in')->default(false)->after('available_for_mobilisation');

            $table->foreignId('approved_by')->nullable()->after('trainer_opt_in')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->string('status_note', 500)->nullable()->after('approved_at');

            $table->index(['status', 'county']);
        });

        // CTPs approved before the fee was tracked are not debtors — mark the fee waived.
        DB::table('ctp_registrations')->whereNotNull('ctp_code')
            ->update(['fee_status' => 'waived']);
    }

    public function down(): void
    {
        Schema::table('ctp_registrations', function (Blueprint $table) {
            $table->dropIndex(['status', 'county']);
            $table->dropConstrainedForeignId('referred_by');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn([
                'gps_lat', 'gps_lng', 'year_left_school', 'certificates_held',
                'fee_status', 'fee_amount', 'fee_method', 'fee_reference', 'fee_paid_at',
                'referred_at', 'service_counties', 'available_for_mobilisation', 'trainer_opt_in',
                'approved_at', 'status_note',
            ]);
            $table->enum('status', ['pending_review', 'active', 'rejected'])->default('pending_review')->change();
        });
    }
};
