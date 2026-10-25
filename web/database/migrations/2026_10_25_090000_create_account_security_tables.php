<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The three things an account now has to survive: a one-time code proving the email is the holder's own,
     * a reset link that dies on its own, and a record of how many links one address asked for today.
     */
    public function up(): void
    {
        Schema::create('email_otps', function (Blueprint $table) {
            $table->id();
            // Null while the account is still being created — the code belongs to the address, not yet to a user.
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('email', 190)->index();
            $table->enum('purpose', ['activate', 'login'])->index();
            $table->string('code_hash');
            // The phone app gets this string back instead of a session, so it can ask to confirm without naming its user.
            $table->string('challenge_hash', 64)->nullable()->unique();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('password_reset_links', function (Blueprint $table) {
            $table->id();
            $table->string('email', 190);
            $table->string('token_hash')->unique();
            // How many links this address asked for is counted from here, so the daily cap is per person, not per device.
            $table->unsignedTinyInteger('sequence')->default(1);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['email', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_links');
        Schema::dropIfExists('email_otps');
    }
};
