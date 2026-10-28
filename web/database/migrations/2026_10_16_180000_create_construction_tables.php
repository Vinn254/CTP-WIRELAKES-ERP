<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module 8 — Construction & Infrastructure. The construction file a project gets when the work is buildable
 * rather than a day's job: what the site is like, what the design costs, who turned up, what the inspector found and
 * what changed after the price was agreed. Money is still Module 11's and the order's own progress is still Module 7's —
 * this file holds the engineering record those two read from.
 */
return new class extends Migration
{
    public function up(): void
    {
        //  — an assessment is a visit on a date, by a person. A later visit is a new row, never an edit.
        Schema::create('site_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->date('visited_on');
            $table->string('ground_conditions', 500)->nullable();
            $table->string('access', 500)->nullable();
            $table->string('utilities', 500)->nullable();
            $table->string('risks', 1000)->nullable();
            $table->text('findings');
            $table->string('recommendation', 30)->default('proceed');
            $table->string('photo_path', 500)->nullable();
            $table->string('photo_name', 160)->nullable();
            $table->string('photo_size', 30)->nullable();
            $table->foreignId('assessed_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'visited_on']);
        });

        //  — the bill of quantities. Every line carries its own unit cost so the total is a sum, not a typed figure.
        Schema::create('boq_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('drawing_ref', 80)->nullable();
            $table->string('trade', 40)->nullable();
            $table->string('description', 500);
            $table->string('unit', 30)->default('each');
            $table->decimal('quantity', 12, 3)->default(0);
            $table->decimal('unit_cost_ksh', 14, 2)->default(0);
            $table->unsignedInteger('position')->default(0);
            // A line the design did not have is marked with the variation that brought it in.
            $table->string('source', 20)->default('design');
            $table->foreignId('variation_order_id')->nullable();
            // A line that should not be built is withdrawn with a reason and stays on the file — a BoQ is never emptied.
            $table->string('status', 20)->default('live');
            $table->string('withdraw_note', 1000)->nullable();
            $table->foreignId('withdrawn_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'position']);
        });

        //  — labour. Fundis and sub-contractors counted by day and hour, against the project and its order.
        Schema::create('labour_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->date('worked_on');
            $table->string('worker_name', 160);
            $table->foreignId('fundi_id')->nullable()->constrained('fundi_registrations')->nullOnDelete();
            $table->string('sub_contractor', 160)->nullable();
            $table->string('trade', 40)->nullable();
            $table->decimal('hours', 6, 2)->default(8);
            $table->decimal('rate_ksh', 12, 2)->default(0);
            $table->string('note', 500)->nullable();
            $table->foreignId('recorded_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'worked_on']);
        });

        //  — an inspection is scheduled, then reported item by item, then signed off. A failed item keeps the
        // worker's proof and the re-check on the same row so the loop is readable.
        Schema::create('site_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->date('scheduled_on');
            $table->date('inspected_on')->nullable();
            $table->string('purpose', 500)->nullable();
            $table->string('status', 20)->default('scheduled');
            $table->text('report_note')->nullable();
            $table->string('photo_path', 500)->nullable();
            $table->string('photo_name', 160)->nullable();
            $table->string('photo_size', 30)->nullable();
            $table->foreignId('inspector_user')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('signed_off_on')->nullable();
            $table->foreignId('signed_off_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'status']);
        });

        Schema::create('site_inspection_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_id')->constrained('site_inspections')->cascadeOnDelete();
            $table->string('item', 500);
            $table->string('result', 20)->default('pending');
            $table->string('note', 1000)->nullable();
            //  — the worker's proof of the fix, and the re-check that clears it.
            $table->string('proof_path', 500)->nullable();
            $table->string('proof_name', 160)->nullable();
            $table->string('proof_size', 30)->nullable();
            $table->string('proof_note', 1000)->nullable();
            $table->timestamp('proof_on')->nullable();
            $table->foreignId('proved_by_fundi')->nullable()->constrained('fundi_registrations')->nullOnDelete();
            $table->date('rechecked_on')->nullable();
            $table->timestamps();

            $table->index(['inspection_id', 'result']);
        });

        //  — a change to agreed scope. Staff raise it, a Director approves or rejects it, and the approved cost
        // moves the work order and adds its lines to the BoQ.
        Schema::create('variation_orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->text('scope');
            $table->string('reason', 1000)->nullable();
            $table->decimal('extra_cost_ksh', 14, 2)->default(0);
            $table->string('extra_materials', 1000)->nullable();
            $table->unsignedSmallInteger('extra_days')->default(0);
            $table->string('status', 20)->default('proposed');
            $table->foreignId('raised_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_on')->nullable();
            $table->string('decision_note', 1000)->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variation_orders');
        Schema::dropIfExists('site_inspection_items');
        Schema::dropIfExists('site_inspections');
        Schema::dropIfExists('labour_entries');
        Schema::dropIfExists('boq_items');
        Schema::dropIfExists('site_assessments');
    }
};
