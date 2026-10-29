<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A mark sheet carries a CAT and an assignment for each unit, and an approved sheet writes those marks onto a
 * trainee's record. The column was created before the marks a trainer records, so it has to hold them now.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("alter table progress_entries modify kind enum('attendance','theory','practical','cat','assignment','assessment','note') not null default 'note'");
    }

    public function down(): void
    {
        DB::statement("alter table progress_entries modify kind enum('attendance','theory','practical','assessment','note') not null default 'note'");
    }
};
