<?php

use App\Support\Accounts;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * One person, one account: every stored identity is brought to the form the app now compares it in.
     * Emails go lower case, phones lose the spaces people type, National IDs take one case.
     */
    public function up(): void
    {
        foreach (config('account.identity_tables') as $table => $columns) {
            foreach ($columns as $column => $kind) {
                $rows = DB::table($table)->whereNotNull($column)->get(['id', $column]);

                foreach ($rows as $row) {
                    $value = $row->{$column};
                    $fixed = match ($kind) {
                        'email' => Accounts::email($value),
                        'phone' => Accounts::phone($value),
                        default => Accounts::idNumber($value),
                    };

                    if ($fixed !== '' && $fixed !== $value) {
                        DB::table($table)->where('id', $row->id)->update([$column => $fixed]);
                    }
                }
            }
        }
    }
};
