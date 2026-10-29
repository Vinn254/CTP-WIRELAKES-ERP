<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The area pages show short clips next to their photos, so the content editor needs a video field type.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE content_fields MODIFY type ENUM('text','textarea','image','video','url','price') NOT NULL DEFAULT 'text'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE content_fields MODIFY type ENUM('text','textarea','image','url','price') NOT NULL DEFAULT 'text'");
    }
};
