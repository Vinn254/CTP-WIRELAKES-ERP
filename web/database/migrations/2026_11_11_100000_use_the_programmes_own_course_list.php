<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The programme's own course list, exactly as the office wrote it.
 *
 * The catalogue was seeded with short demo names. Six of them are the same trades under the programme's real names,
 * so those rows are **renamed where they stand** — a cohort, a mark sheet or a certificate already tied to one keeps
 * its history and simply reads the right course. Four courses the programme runs and the app did not have are added,
 * and the seven names that are not on the programme's list are taken off the list for new cohorts. Nothing is
 * deleted: any of them can be relisted on Training, and a student already sitting one keeps their record.
 */
return new class extends Migration
{
    /** The six demo rows, under the course name the programme uses, with the code the office writes it as. */
    public const RENAMED = [
        'ICT' => ['Computer & Cybersecurity Technology', 'CCT'],
        'Electrical' => ['Electrical & Electronics Technology', 'EET'],
        'Construction' => ['Construction Technology', 'CT'],
        'Plumbing' => ['Plumbing & Sanitary Technology', 'PST'],
        'Welding' => ['Welding & Metal Fabrication Technology', 'WMFT'],
        'Solar Installation' => ['Solar & Renewable Energy Technology', 'SRET'],
    ];

    /** Courses the programme runs that the app did not carry. The office adds a summary; none is invented here. */
    public const ADDED = [
        ['Automotive & Heavy Duty Equipment Technology', 'AHDET'],
        ['Building Design & Architecture Support Technology', 'BDAST'],
        ['Civil & Structural Engineering Technology', 'CSET'],
        ['Equipment Maintenance & Safety Technology', 'EMST'],
    ];

    /** Not on the programme's list — off the shelves for new cohorts, kept for anyone already sitting one. */
    public const CLOSED = ['Networking', 'Programming', 'Robotics', 'AI', 'E-Waste', 'Carpentry', 'Mathematics'];

    public function up(): void
    {
        foreach (self::RENAMED as $old => [$name, $code]) {
            DB::table('trainings')->where('name', $old)->update([
                'name' => $name, 'code' => $code, 'updated_at' => now(),
            ]);
        }

        foreach (self::ADDED as [$name, $code]) {
            DB::table('trainings')->where('name', $name)->exists()
                ? DB::table('trainings')->where('name', $name)->update(['status' => 'active'])
                : DB::table('trainings')->insert([
                    'name' => $name, 'code' => $code, 'status' => 'active',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
        }

        DB::table('trainings')->whereIn('name', self::CLOSED)->update(['status' => 'inactive', 'updated_at' => now()]);
    }

    public function down(): void
    {
        foreach (self::RENAMED as $old => [$name, $code]) {
            DB::table('trainings')->where('name', $name)->update(['name' => $old, 'code' => strtoupper(str_replace(' ', '', $old))]);
        }

        DB::table('trainings')->whereIn('name', array_column(self::ADDED, 0))->update(['status' => 'active']);
        DB::table('trainings')->whereIn('name', self::CLOSED)->update(['status' => 'active']);
    }
};
