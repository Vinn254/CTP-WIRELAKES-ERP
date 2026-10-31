<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The internship grade reads just "Intern" now, and the FAQ says the same. The stored CMS copy is
 * renamed with it, since the public form and the register both show the CMS label.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->rename('Fundi-Intern', 'Intern');
        $this->rewriteFaq();
    }

    public function down(): void
    {
        $this->rename('Intern', 'Fundi-Intern');
    }

    private function rename(string $from, string $to): void
    {
        if (! Schema::hasTable('content_values')) {
            return;
        }

        $field = DB::table('content_fields')
            ->join('content_sections', 'content_sections.id', '=', 'content_fields.section_id')
            ->where('content_sections.page', 'membership')
            ->where('content_sections.key', 'fundi_tiers')
            ->where('content_fields.key', 'label')
            ->select('content_fields.id')
            ->first();

        if (! $field) {
            return;
        }

        DB::table('content_values')
            ->where('field_id', $field->id)
            ->where('text_value', $from)
            ->update(['text_value' => $to]);
    }

    private function rewriteFaq(): void
    {
        if (! Schema::hasTable('content_values')) {
            return;
        }

        $ids = DB::table('content_fields')
            ->join('content_sections', 'content_sections.id', '=', 'content_fields.section_id')
            ->where('content_sections.page', 'faq')
            ->where('content_sections.key', 'items')
            ->pluck('content_fields.id');

        if ($ids->isEmpty()) {
            return;
        }

        DB::table('content_values')
            ->whereIn('field_id', $ids)
            ->where('text_value', 'like', '%Fundi-Intern%')
            ->get(['id', 'text_value'])
            ->each(function ($row) {
                $text = str_replace(
                    'What is the difference between a Fundi-Intern,',
                    'What is the difference between an Intern,',
                    $row->text_value
                );

                DB::table('content_values')->where('id', $row->id)
                    ->update(['text_value' => str_replace('Fundi-Intern', 'Intern', $text)]);
            });
    }
};
