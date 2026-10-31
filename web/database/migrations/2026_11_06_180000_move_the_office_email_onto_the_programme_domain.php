<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The programme has its own mailboxes on wirelakestechnologies.com, so the address printed on the site and quoted
 * on member letters moves off the old placeholder domain. Anything the office later edits on the content screen
 * still wins — this only carries the seeded copy across.
 */
return new class extends Migration
{
    private const FROM = 'info@ctp-wirelakes.ke';

    private const TO = 'info@wirelakestechnologies.com';

    public function up(): void
    {
        $this->move(self::FROM, self::TO);
    }

    public function down(): void
    {
        $this->move(self::TO, self::FROM);
    }

    private function move(string $from, string $to): void
    {
        if (Schema::hasTable('content_values')) {
            DB::table('content_values')->where('text_value', $from)->update(['text_value' => $to]);
        }

        if (Schema::hasTable('settings')) {
            DB::table('settings')->where('key', 'programme_email')->where('value', $from)->update(['value' => $to]);
        }
    }
};
