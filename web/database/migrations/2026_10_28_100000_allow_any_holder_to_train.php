<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A trainer is a person the programme has licensed to deliver a training, and that person is not always an E-Fundi.
 * A robotics trainer engaged for one project, a facilitator the office already employs and a senior fundi all stand
 * in the same queue and work the same desk, so the posting now names its holder by one of three records instead of
 * demanding a fundi row. What they are licensed to teach — the programmes the office has added — is what shapes
 * their interface, not who they are.
 *
 * One posting per holder and site is kept by a unique pair on each of the three holder columns: a row of another
 * kind leaves the column NULL, and MySQL never counts two NULLs as a clash.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('trainers', 'holder_type')) {
            Schema::table('trainers', function (Blueprint $table) {
                $table->enum('holder_type', ['fundi', 'staff', 'applicant'])->default('fundi')->after('id');
            });
        }

        if (! Schema::hasColumn('trainers', 'user_id')) {
            Schema::table('trainers', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->after('fundi_id')->constrained('users')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('trainers', 'job_application_id')) {
            Schema::table('trainers', function (Blueprint $table) {
                $table->foreignId('job_application_id')->nullable()->after('user_id')
                    ->constrained('job_applications')->nullOnDelete();
            });
        }

        $this->unique('trainers', ['user_id', 'venue_id'], 'trainers_staff_site_unique');
        $this->unique('trainers', ['job_application_id', 'venue_id'], 'trainers_applicant_site_unique');

        Schema::table('trainers', function (Blueprint $table) {
            $table->unsignedBigInteger('fundi_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('trainers', function (Blueprint $table) {
            $table->dropUnique('trainers_staff_site_unique');
            $table->dropUnique('trainers_applicant_site_unique');
            $table->dropConstrainedForeignId('user_id');
            $table->dropConstrainedForeignId('job_application_id');
            $table->dropColumn('holder_type');
        });
    }

    private function unique(string $table, array $columns, string $name): void
    {
        $existing = collect(DB::select('SHOW INDEX FROM '.$table))->pluck('Key_name')->unique();

        if ($existing->contains($name)) {
            return;
        }

        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unique($columns, $name));
    }
};
