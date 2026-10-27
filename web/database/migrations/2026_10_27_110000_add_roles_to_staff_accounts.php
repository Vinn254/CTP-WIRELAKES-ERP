<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Job titles in the office. A staff account now names the roles it holds as well as the departments that opens,
 * and an invitation carries the whole set instead of one department, so a person is never hired into a single
 * module and then re-invited to reach the next one.
 *
 * The invitation column stays one list of slugs rather than becoming a second table: nothing reads it before the
 * account exists, and an old invitation naming one department is already a valid one-item list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('roles')->nullable()->after('role');
        });

        Schema::table('staff_invites', function (Blueprint $table) {
            $table->string('department', 190)->nullable()->change();
            $table->string('roles', 190)->nullable()->after('department');
        });
    }

    public function down(): void
    {
        Schema::table('staff_invites', function (Blueprint $table) {
            $table->dropColumn('roles');
            $table->string('department', 60)->nullable()->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('roles');
        });
    }
};
