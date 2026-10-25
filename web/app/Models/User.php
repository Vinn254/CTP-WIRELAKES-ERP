<?php

namespace App\Models;

use Database\Factories\UserFactory;
use App\Models\Concerns\NormalizedIdentity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

// Role, active and departments decide who opens a department or releases money, so they are never mass-assigned:
// only the office screens that own them write them, and they do it with forceFill().
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, NormalizedIdentity;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
            'departments' => 'array',
        ];
    }

    /** The department (module) slugs this staff account may open — empty for a member or an unscoped admin. */
    public function departmentSlugs(): array
    {
        return array_values(array_filter((array) ($this->departments ?? [])));
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'superadmin';
    }
}
