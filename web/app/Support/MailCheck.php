<?php

namespace App\Support;

use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Shared hosting gives the office no shell, so the post is checked from a screen: this hands one message to
 * whatever .env configures and reports the answer the mail server actually gave, instead of a silent nothing.
 */
class MailCheck
{
    /** What the app would use to send, with the password left out. */
    public static function settings(): array
    {
        $smtp = config('mail.mailers.smtp');

        return [
            'mailer' => (string) config('mail.default'),
            'host' => (string) ($smtp['host'] ?? ''),
            'port' => (string) ($smtp['port'] ?? ''),
            'scheme' => (string) ($smtp['scheme'] ?: 'plain'),
            'username' => (string) ($smtp['username'] ?? ''),
            'from' => (string) config('mail.from.address'),
        ];
    }

    /** Post a test message to one address and answer in a line what happened. */
    public static function deliver(string $email): array
    {
        $s = static::settings();

        if ($s['mailer'] !== 'smtp') {
            return ['ok' => false, 'message' => 'The app is not sending at all right now — MAIL_MAILER in .env is "'.
                $s['mailer'].'", which writes emails into a log file. Set it to smtp.'];
        }

        if ($s['from'] === '' || $s['host'] === '') {
            return ['ok' => false, 'message' => 'MAIL_FROM_ADDRESS or MAIL_HOST is empty in .env, so nothing can be sent.'];
        }

        $via = $s['host'].':'.$s['port'].' ('.$s['scheme'].') as '.$s['from'];

        try {
            Mail::raw(
                'This is a delivery test from the '.config('app.name').' office. If you can read it, the mail settings are working.',
                fn ($m) => $m->to($email)->subject('CTP-WireLakes email delivery test')
            );
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Sending through '.$via.' failed: '.$e->getMessage()];
        }

        return ['ok' => true, 'message' => 'A test email went out through '.$via.' to '.$email.
            '. Check that inbox, and its junk folder, before sending anything real.'];
    }
}
