<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A message from the public site used to arrive at one unread pile, so a question about e-waste sat beside a
 * donation query until somebody opened the screen. The sender now names what it is about, and that answer is kept
 * on the message so the office sees which desk it was sent to.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('contact_messages', 'topic')) {
            Schema::table('contact_messages', function (Blueprint $table) {
                $table->string('topic', 40)->nullable()->after('email');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('contact_messages', 'topic')) {
            Schema::table('contact_messages', function (Blueprint $table) {
                $table->dropColumn('topic');
            });
        }
    }
};
