<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module 7 — Project Execution & Payment. The work order is the paper the award turns into: who is doing the
 * work, what they are delivering, when it starts and ends, how it is paid for and what has actually happened on site.
 * Money is not calculated here — the payout register in Module 11 stays the only place a figure is worked out.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            // The worker as the award recorded them, kept as text as well so the order still reads after a register change.
            $table->string('assignee_type', 20)->default('fundi');
            $table->unsignedBigInteger('assignee_id')->nullable();
            $table->string('assignee_name', 160)->nullable();
            $table->string('status', 30)->default('issued');
            $table->text('scope')->nullable();
            $table->text('deliverables')->nullable();
            $table->date('start_on')->nullable();
            $table->date('deadline')->nullable();
            //  — how the agreed amount is released. The split is stored so a custom 50/20/30 stays visible.
            $table->string('schedule', 20)->default('fixed');
            $table->json('split')->nullable();
            $table->decimal('agreed_amount_ksh', 14, 2)->nullable();
            $table->string('materials_note', 1000)->nullable();
            //  — the fundi accepts the order, types their signature and confirms the start date.
            $table->timestamp('accepted_on')->nullable();
            $table->string('signature', 160)->nullable();
            $table->string('accept_note', 1000)->nullable();
            $table->unsignedTinyInteger('percent_complete')->default(0);
            $table->timestamp('completion_requested_on')->nullable();
            //  — the hirer inspects. The office records what they said, with the signed sheet attached.
            $table->timestamp('confirmed_on')->nullable();
            $table->string('confirmed_by_name', 160)->nullable();
            $table->string('confirmation_note', 1000)->nullable();
            $table->string('confirmation_path', 500)->nullable();
            $table->string('confirmation_name', 160)->nullable();
            $table->timestamp('completed_on')->nullable();
            $table->timestamp('closed_on')->nullable();
            $table->string('closure_note', 1000)->nullable();
            $table->foreignId('raised_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['assignee_type', 'assignee_id', 'status']);
        });

        Schema::create('work_order_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->string('name', 160);
            $table->unsignedTinyInteger('share_percent')->default(0);
            $table->decimal('amount_ksh', 14, 2)->nullable();
            $table->date('due_on')->nullable();
            $table->string('status', 20)->default('pending');
            //  — a supervisor or manager signs a milestone off; the office types who and when.
            $table->timestamp('signed_on')->nullable();
            $table->foreignId('signed_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 1000)->nullable();
            $table->timestamps();

            $table->index(['work_order_id', 'status']);
        });

        Schema::create('work_order_updates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            // progress = the fundi's day, visit = a supervisor's site visit, completion = the final photos.
            $t->string('kind', 20)->default('progress');
            $t->date('entry_date');
            $t->unsignedTinyInteger('percent')->nullable();
            $t->text('note')->nullable();
            $t->string('photo_path', 500)->nullable();
            $t->string('photo_name', 160)->nullable();
            $t->string('photo_size', 30)->nullable();
            $t->foreignId('recorded_by_user')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('recorded_by_fundi')->nullable()->constrained('fundi_registrations')->nullOnDelete();
            $t->timestamps();

            $t->index(['work_order_id', 'entry_date']);
        });

        Schema::create('work_order_disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->text('issue');
            $table->string('issue_path', 500)->nullable();
            $table->string('issue_name', 160)->nullable();
            $table->string('issue_size', 30)->nullable();
            $table->foreignId('raised_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('raised_on');
            //  — the fundi has 48 hours to answer, then the Programme Manager mediates and a Director decides.
            $table->timestamp('responded_on')->nullable();
            $table->text('response')->nullable();
            $table->foreignId('responded_by_fundi')->nullable()->constrained('fundi_registrations')->nullOnDelete();
            $table->foreignId('mediated_by_user')->nullable()->constrained('users')->nullOnDelete();
            // Mediation and the decision are two separate records: the note on the decision never overwrites what was tried.
            $table->text('mediation_note')->nullable();
            $table->string('outcome', 20)->nullable();
            $table->decimal('penalty_ksh', 14, 2)->nullable();
            $table->string('outcome_note', 1000)->nullable();
            $table->foreignId('decided_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_on')->nullable();
            $table->timestamps();

            $table->index(['work_order_id', 'outcome']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_disputes');
        Schema::dropIfExists('work_order_updates');
        Schema::dropIfExists('work_order_milestones');
        Schema::dropIfExists('work_orders');
    }
};
