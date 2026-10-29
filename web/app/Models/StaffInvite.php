<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffInvite extends Model
{
    public const ROLES = ['admin' => 'Admin (department staff)', 'superadmin' => 'Super admin (full access)'];

    protected $fillable = ['name', 'email', 'role', 'department', 'roles', 'trainer_training_id', 'token', 'invited_by', 'status', 'accepted_user_id', 'expires_at'];

    protected $casts = ['expires_at' => 'datetime'];

    public function trainerField()
    {
        return $this->belongsTo(Training::class, 'trainer_training_id');
    }

    public function isUsable(): bool
    {
        return $this->status === 'pending' && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /**
     * One column holds the whole list because nothing reads an invitation before the account exists, and an
     * invitation raised before this change already sits there as a valid one-item list.
     */
    public static function list(?array $slugs): ?string
    {
        $slugs = array_values(array_unique(array_filter((array) $slugs)));

        return $slugs === [] ? null : implode(',', $slugs);
    }

    /** @return array<int, string> the office job titles held on this invitation */
    public function roleSlugs(): array
    {
        return array_filter(explode(',', (string) $this->roles));
    }

    /** @return array<int, string> the departments this invitation grants */
    public function departmentSlugs(): array
    {
        return array_filter(explode(',', (string) $this->department));
    }

    public function roleLabels(): string
    {
        return implode(' · ', \App\Support\Roles::namesFor($this->roleSlugs()));
    }
}
