<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffInvite extends Model
{
    public const ROLES = ['admin' => 'Admin (department staff)', 'superadmin' => 'Super admin (full access)'];

    protected $fillable = ['name', 'email', 'role', 'department', 'token', 'invited_by', 'status', 'accepted_user_id', 'expires_at'];

    protected $casts = ['expires_at' => 'datetime'];

    public function isUsable(): bool
    {
        return $this->status === 'pending' && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
