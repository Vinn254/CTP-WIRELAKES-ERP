<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module 10 — the e-waste register. A collection starts life in the field: a CTP or an E-Fundi registers
 * what they found, with its condition and a photo. An E-Fundi takes it to a collection point and forwards it, and
 * the programme office weighs it in. The reference is issued on registration so the field and the store always
 * talk about the same item.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ewaste_collections', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->date('collected_on');
            $table->foreignId('community_id')->nullable()->constrained('communities')->nullOnDelete();
            $table->string('county', 100)->nullable();
            $table->string('ward', 120)->nullable();
            // Who put it in: a CTP in the field or an E-Fundi. Exactly one of the two.
            $table->foreignId('ctp_id')->nullable()->constrained('ctp_registrations')->nullOnDelete();
            $table->foreignId('fundi_id')->nullable()->constrained('fundi_registrations')->nullOnDelete();
            $table->string('item_type', 60);
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('estimated_weight_kg', 9, 2)->nullable();
            $table->decimal('weighed_weight_kg', 9, 2)->nullable();
            $table->string('condition', 20)->default('mixed');
            $table->string('status', 20)->default('submitted');
            $table->string('note', 1000)->nullable();
            $table->string('photo_path', 500)->nullable();
            $table->string('photo_name', 190)->nullable();
            $table->unsignedInteger('photo_size')->nullable();
            // The E-Fundi who took it to a collection point, and the office that weighed it in.
            $table->foreignId('forwarded_by_fundi')->nullable()->constrained('fundi_registrations')->nullOnDelete();
            $table->timestamp('forwarded_on')->nullable();
            $table->string('forward_note', 1000)->nullable();
            $table->foreignId('received_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_on')->nullable();
            $table->timestamps();
            $table->index(['status', 'collected_on']);
            $table->index(['county', 'item_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ewaste_collections');
    }
};
