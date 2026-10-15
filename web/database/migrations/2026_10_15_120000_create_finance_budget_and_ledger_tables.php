<?php

use App\Models\BudgetLine;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module 11 — the money backbone (§14.2, §14.3). A budget line is one programme category for one financial year;
 * every shilling that moves afterwards is a ledger entry that either feeds a budget line (an expense charged to it)
 * or arrives as funding. The lists stay closed so the totals on the budget page always add up to the same eight
 * categories the programme is planned around.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('fiscal_year');
            $table->string('category', 32);
            $table->decimal('amount_ksh', 14, 2)->default(0);
            $table->string('note', 1000)->nullable();
            $table->foreignId('set_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['fiscal_year', 'category']);
        });

        Schema::create('finance_entries', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->unsignedSmallInteger('fiscal_year');
            $table->string('direction', 10);
            // What kind of money it is — the closed list in §14.3.
            $table->string('kind', 40);
            // Which budget line an expense is charged against (null on funding in).
            $table->string('budget_category', 32)->nullable();
            $table->string('title', 190);
            $table->decimal('amount_ksh', 14, 2);
            $table->string('method', 20)->default('mpesa');
            $table->date('happened_on');
            $table->string('note', 1000)->nullable();
            // What the money belongs to, so a project, a cohort or a purchase order carries its own account.
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cohort_id')->nullable()->constrained('cohorts')->nullOnDelete();
            $table->foreignId('community_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ctp_id')->nullable()->constrained('ctp_registrations')->nullOnDelete();
            $table->foreignId('fundi_id')->nullable()->constrained('fundi_registrations')->nullOnDelete();
            // A line the system posted itself (a paid purchase order, a released payout) points back at its source.
            $table->string('source_type', 60)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('recorded_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['fiscal_year', 'direction']);
            $table->index(['kind', 'happened_on']);
            $table->index(['budget_category']);
            $table->index(['source_type', 'source_id']);
        });

        // §14.2 — the programme plan for the current year, as the office first meets it.
        $rows = [];
        foreach (BudgetLine::PLAN_2026 as $category => $amount) {
            $rows[] = [$category, $amount];
        }

        DB::table('budget_lines')->insert(collect($rows)->map(fn ($r, $i) => [
            'fiscal_year' => 2026,
            'category' => $r[0],
            'amount_ksh' => $r[1],
            'note' => $i === 0 ? 'Taken from the programme budget in the blueprint (§14.2).' : null,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all());
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_entries');
        Schema::dropIfExists('budget_lines');
    }
};
