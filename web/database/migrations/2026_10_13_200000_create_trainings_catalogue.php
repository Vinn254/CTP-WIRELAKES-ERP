<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The list of trainings the programme itself runs (§7.4). A cohort is an intake of one of these, so staff choose
 * from this table instead of typing a course name, and a trainer is registered against it. Anything new the
 * programme starts running is added here by the office first.
 */
return new class extends Migration
{
    public const TRAININGS = [
        'ICT' => 'Computer hardware and software, diagnostics and repair',
        'Networking' => 'Cabling, routers, switching and network setup',
        'Programming' => 'Writing and testing software',
        'Robotics' => 'Building and programming robots, STEM kits',
        'AI' => 'Applied artificial intelligence tools',
        'Electrical' => 'Household and building electrical work',
        'Construction' => 'Building work, masonry, plumbing and finishing',
        'E-Waste' => 'Collection, sorting, dismantling and safe disposal of e-waste',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('trainings')) {
            Schema::create('trainings', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120)->unique();
                $table->string('code', 30)->unique();
                $table->string('summary', 500)->nullable();
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->timestamps();
            });

            DB::table('trainings')->insert(collect(self::TRAININGS)->map(fn ($blurb, $name) => [
                'name' => $name,
                'code' => strtoupper(str_replace([' ', '-'], '', $name)),
                'summary' => $blurb,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ])->all());
        }

        if (Schema::hasColumn('cohorts', 'family')) {
            Schema::table('cohorts', function (Blueprint $table) {
                $table->unsignedBigInteger('training_id')->nullable()->after('reference');
            });

            foreach (DB::table('cohorts')->get(['id', 'family']) as $cohort) {
                DB::table('cohorts')->where('id', $cohort->id)->update([
                    'training_id' => DB::table('trainings')->where('name', $cohort->family)->value('id'),
                ]);
            }
        }

        if (! Schema::hasTable('trainer_training')) {
            Schema::create('trainer_training', function (Blueprint $table) {
                $table->id();
                $table->foreignId('trainer_id')->constrained('trainers')->cascadeOnDelete();
                $table->foreignId('training_id')->constrained('trainings')->cascadeOnDelete();
                $table->unique(['trainer_id', 'training_id']);
            });

            if (Schema::hasColumn('trainers', 'teaches')) {
                foreach (DB::table('trainers')->whereNotNull('teaches')->get(['id', 'teaches']) as $trainer) {
                    foreach ((array) json_decode($trainer->teaches, true) as $family) {
                        $id = DB::table('trainings')->where('name', $family)->value('id');
                        if ($id && ! DB::table('trainer_training')->where([
                            ['trainer_id', $trainer->id],
                            ['training_id', $id],
                        ])->exists()) {
                            DB::table('trainer_training')->insert([
                                'trainer_id' => $trainer->id,
                                'training_id' => $id,
                            ]);
                        }
                    }
                }
            }
        }

        // Every cohort must sit under one of the programme's trainings.
        DB::table('cohorts')->whereNull('training_id')->update([
            'training_id' => DB::table('trainings')->where('name', 'ICT')->value('id'),
        ]);

        // The old nullable FKs (SET NULL) are incompatible with the rebuilt column order.
        Schema::table('cohorts', function (Blueprint $table) {
            $table->dropForeign(['training_id']);
            $table->dropForeign(['trainer_id']);
        });

        if (Schema::hasColumn('cohorts', 'course')) {
            Schema::table('cohorts', function (Blueprint $table) {
                $table->dropColumn(['course', 'family']);
            });
        }

        Schema::table('cohorts', function (Blueprint $table) {
            $table->foreignId('training_id')->nullable(false)->change();
            $table->foreignId('trainer_id')->nullable()->change()->constrained('trainers')->nullOnDelete();
            $table->foreign('training_id')->references('id')->on('trainings')->restrictOnDelete();
        });

        if (Schema::hasColumn('trainers', 'teaches')) {
            Schema::table('trainers', function (Blueprint $table) {
                $table->dropColumn('teaches');
            });
        }
    }

    public function down(): void
    {
        Schema::table('cohorts', function (Blueprint $table) {
            $table->dropForeign(['training_id']);
            $table->dropForeign(['trainer_id']);
        });

        Schema::table('cohorts', function (Blueprint $table) {
            $table->string('course', 150)->nullable();
            $table->enum('family', array_keys(self::TRAININGS))->nullable();
            $table->dropColumn('training_id');
        });

        foreach (DB::table('cohorts')->get(['id']) as $cohort) {
            DB::table('cohorts')->where('id', $cohort->id)->update([
                'course' => 'ICT training',
                'family' => 'ICT',
            ]);
        }

        Schema::table('cohorts', function (Blueprint $table) {
            $table->string('course', 150)->change()->nullable(false);
            $table->enum('family', array_keys(self::TRAININGS))->change()->nullable(false);
            $table->foreignId('trainer_id')->nullable()->change()
                ->constrained('trainers')->nullOnDelete();
        });

        Schema::table('trainers', function (Blueprint $table) {
            $table->json('teaches')->nullable();
        });

        Schema::dropIfExists('trainer_training');
        Schema::dropIfExists('trainings');
    }
};
