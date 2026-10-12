<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Partner extends Model
{
    protected $fillable = ['name', 'website', 'contact_email', 'tier', 'logo_path', 'is_listed'];

    protected $casts = ['is_listed' => 'boolean'];
}
