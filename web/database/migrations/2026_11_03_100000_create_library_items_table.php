<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Registrar's library: the books, notes, slides and PDFs a CTP student reads outside class. It hangs on a
 * course rather than a cohort, so one copy serves every student taking that training — and a reading with no
 * course belongs to every student on the programme.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_items', function (Blueprint $table) {
            $table->id();
            $table->string('title', 190);
            $table->string('kind', 30);
            $table->unsignedBigInteger('training_id')->nullable();
            $table->text('note')->nullable();
            $table->string('file_path', 500);
            $table->string('file_name', 250);
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('status', 10)->default('on');
            $table->unsignedBigInteger('posted_by_user')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->foreign('training_id')->references('id')->on('trainings')->nullOnDelete();
            $table->foreign('posted_by_user')->references('id')->on('users')->nullOnDelete();
            $table->index(['status', 'training_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_items');
    }
};
