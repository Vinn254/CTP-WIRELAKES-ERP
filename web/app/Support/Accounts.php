<?php

namespace App\Support;

use App\Models\CtpRegistration;
use App\Models\FundiRegistration;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * One person, one account. The registers used to keep their own copy of "is this email taken", which let the same
 * address in as a CTP, an E-Fundi and a staff account at once, and let NAME@Example.com sit beside name@example.com.
 * Identity is decided here, once, in lower case — email, phone and National ID each name a person.
 *
 * A hirer is an organisation rather than a person, so it keeps its own register and is not part of this check.
 */
class Accounts
{
    /** Where a person may appear only once, and what a reader calls each of them. */
    private const REGISTERS = [
        CtpRegistration::class => 'CTP',
        FundiRegistration::class => 'E-Fundi',
        User::class => 'staff or member account',
    ];

    public static function email(?string $value): string
    {
        return mb_strtolower(trim((string) $value));
    }

    public static function phone(?string $value): string
    {
        return preg_replace('/\s+/', '', trim((string) $value)) ?? '';
    }

    public static function idNumber(?string $value): string
    {
        return mb_strtoupper(trim((string) $value));
    }

    /**
     * The first identity already held by somebody else, or null when this is a new person.
     *
     * @param  array{email?:string,phone?:string,national_id?:string}  $identity
     * @param  string[]|null  $registers  limit the search, e.g. [User::class] for a login account on its own
     * @return array{field:string,value:string,holder:string,record:Model}|null
     */
    public static function taken(array $identity, ?Model $ignore = null, ?array $registers = null): ?array
    {
        foreach (['email' => 'email', 'phone' => 'phone', 'national_id' => 'id_number'] as $column => $kind) {
            $value = match ($kind) {
                'email' => static::email($identity['email'] ?? null),
                'phone' => static::phone($identity['phone'] ?? null),
                default => static::idNumber($identity['national_id'] ?? null),
            };

            if ($value === '') {
                continue;
            }

            foreach ($registers ?? array_keys(self::REGISTERS) as $class) {
                $query = $class::query()->where($column, $value);

                if ($ignore && $ignore::class === $class && $ignore->exists) {
                    $query->whereKeyNot($ignore->getKey());
                }

                if ($record = $query->first()) {
                    return [
                        'field' => $column,
                        'value' => $value,
                        'holder' => self::REGISTERS[$class] ?? class_basename($class),
                        'record' => $record,
                    ];
                }
            }
        }

        return null;
    }

    /** Stop a registration or an account the moment its identity is somebody else's already. */
    public static function guard(array $identity, ?Model $ignore = null, ?array $registers = null): void
    {
        $taken = static::taken($identity, $ignore, $registers);

        if (! $taken) {
            return;
        }

        throw ValidationException::withMessages([
            $taken['field'] => static::alreadyRegisteredMessage($taken),
        ]);
    }

    /** The line a person is answered with when their address is already on the programme. */
    public static function alreadyRegisteredMessage(array $taken): string
    {
        $what = match ($taken['field']) {
            'phone' => 'That phone number',
            'national_id' => 'That National ID',
            default => 'That email',
        };

        return $what.' already belongs to a '.$taken['holder'].' in this programme. One person has one account — '
            .'sign in with it, or ask the office to update the existing record.';
    }
}
