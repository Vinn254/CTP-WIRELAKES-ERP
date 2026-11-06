<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The scheme of work an intake teaches, with its units.
 *
 * A unit line on the programme only says what the trainee must be able to do. The office holds the actual scheme —
 * week by week, what is covered, what is examined — as a paper, and it asked for that paper to go on the unit itself
 * so a trainee, a lecturer and anyone visiting the site can open it without the office posting it round.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Each column is put on only if it is missing, so a re-run on a host that took some of them changes nothing.
        Schema::table('training_modules', function (Blueprint $table) {
            if (! Schema::hasColumn('training_modules', 'syllabus_path')) {
                $table->string('syllabus_path')->nullable()->after('outcome');
            }

            if (! Schema::hasColumn('training_modules', 'syllabus_name')) {
                $table->string('syllabus_name', 190)->nullable()->after('syllabus_path');
            }

            if (! Schema::hasColumn('training_modules', 'syllabus_uploaded_at')) {
                $table->timestamp('syllabus_uploaded_at')->nullable()->after('syllabus_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('training_modules', function (Blueprint $table) {
            $table->dropColumn(['syllabus_path', 'syllabus_name', 'syllabus_uploaded_at']);
        });
    }
};
