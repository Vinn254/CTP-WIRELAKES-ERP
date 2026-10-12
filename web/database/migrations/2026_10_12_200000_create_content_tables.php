<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_sections', function (Blueprint $table) {
            $table->id();
            $table->string('page', 60)->index();          // home | about | membership | site ...
            $table->string('key', 80);                     // hero | programme_cards | fees ...
            $table->string('name', 120);                   // admin label
            $table->enum('kind', ['single', 'repeater'])->default('single');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
            $table->unique(['page', 'key']);
        });

        Schema::create('content_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained('content_sections')->cascadeOnDelete();
            $table->string('key', 80);
            $table->string('label', 120);
            $table->enum('type', ['text', 'textarea', 'image', 'url', 'price'])->default('text');
            $table->unsignedInteger('sort_order')->default(0);
            $table->unique(['section_id', 'key']);
        });

        Schema::create('content_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained('content_sections')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
        });

        Schema::create('content_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entry_id')->constrained('content_entries')->cascadeOnDelete();
            $table->foreignId('field_id')->constrained('content_fields')->cascadeOnDelete();
            $table->text('text_value')->nullable();
            $table->string('image_path', 300)->nullable();
            $table->unique(['entry_id', 'field_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_values');
        Schema::dropIfExists('content_entries');
        Schema::dropIfExists('content_fields');
        Schema::dropIfExists('content_sections');
    }
};
