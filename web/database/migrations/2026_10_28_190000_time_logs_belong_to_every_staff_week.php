<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A week sheet belongs to a person, not to a training posting. Every office account fills its own week now — Finance,
 * Human Resources, Technology, all of them — so the log is keyed on the staff account and the trainer posting link
 * becomes history that still prints. Weeks are picked by month and by which week of that month it is.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('trainer_time_logs', 'time_logs');

        Schema::table('time_logs', function (Blueprint $t) {
            $t->foreignId('user_id')->nullable()->after('id');
            $t->foreignId('trainer_id')->nullable()->change();
        });

        DB::statement('UPDATE time_logs tl
            LEFT JOIN trainers tr ON tr.id = tl.trainer_id
            LEFT JOIN fundi_registrations f ON f.id = tr.fundi_id
            SET tl.user_id = COALESCE(tr.user_id, f.user_id)
            WHERE tl.user_id IS NULL');

        foreach (Schema::getIndexes('time_logs') as $index) {
            if (($index['name'] ?? '') !== 'primary' && ($index['columns'] ?? []) === ['trainer_id', 'log_date']) {
                Schema::table('time_logs', fn (Blueprint $t) => $t->dropUnique($index['name']));
            }
        }

        Schema::table('time_logs', function (Blueprint $t) {
            $t->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $t->unique(['user_id', 'log_date']);
            $t->index(['user_id', 'week_start']);
            $t->index(['status', 'week_start']);
        });
    }

    public function down(): void
    {
        Schema::table('time_logs', function (Blueprint $t) {
            $t->dropUnique(['user_id', 'log_date']);
            $t->dropIndex(['user_id', 'week_start']);
            $t->dropIndex(['status', 'week_start']);
            $t->dropForeign(['user_id']);
            $t->dropColumn('user_id');
        });

        Schema::rename('time_logs', 'trainer_time_logs');
    }
};
