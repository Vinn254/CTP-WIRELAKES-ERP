<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Donation extends Model
{
    protected $fillable = [
        'donor_name', 'email', 'phone', 'amount', 'currency',
        'method', 'frequency', 'impact_label', 'message', 'anonymous',
    ];

    protected $casts = ['amount' => 'decimal:2', 'anonymous' => 'boolean'];
}
