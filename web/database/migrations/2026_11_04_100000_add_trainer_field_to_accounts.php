<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A trainer teaches a field, so the office records which one when it hands over the Trainer title. It sits on the
 * account (and on the invitation that made it) rather than on a site posting, because the person's field does not
 * change with the venue they are sent to.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['users', 'staff_invites'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unsignedBigInteger('trainer_training_id')->nullable()->after('roles');
            });
        }
    }

    public function down(): void
    {
        foreach (['users', 'staff_invites'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('trainer_training_id');
            });
        }
    }
};
