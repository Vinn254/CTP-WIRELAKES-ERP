<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AccountInvite extends Model
{
    protected $fillable = [
        'member_type', 'member_id', 'name', 'email', 'token',
        'status', 'accepted_user_id', 'invited_by', 'expires_at',
    ];

    protected $casts = ['expires_at' => 'datetime'];

    public function isUsable(): bool
    {
        return $this->status === 'pending' && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function member(): Model
    {
        return $this->member_type === 'fundi'
            ? FundiRegistration::find($this->member_id)
            : CtpRegistration::find($this->member_id);
    }

    public static function issueFor(Model $member, ?int $staffId = null, bool $force = false): self
    {
        $type = $member instanceof FundiRegistration ? 'fundi' : 'ctp';
        $code = $type === 'fundi' ? ($member->ef_code ?? '') : ($member->ctp_code ?? '');

        $existing = self::where('member_type', $type)->where('member_id', $member->id)->first();
        if ($existing) {
            // An accepted invite means the account already exists — never revoke it.
            if ($existing->status === 'accepted' || ($existing->isUsable() && ! $force)) {
                return $existing;
            }

            $existing->update([
                'name' => $member->full_name,
                'email' => $member->email,
                'token' => $code.'-'.Str::random(40),
                'status' => 'pending',
                'expires_at' => now()->addDays(30),
                'invited_by' => $staffId,
            ]);

            return $existing;
        }

        return self::create([
            'member_type' => $type,
            'member_id' => $member->id,
            'name' => $member->full_name,
            'email' => $member->email,
            'token' => $code.'-'.Str::random(40),
            'expires_at' => now()->addDays(30),
            'invited_by' => $staffId,
        ]);
    }
}
