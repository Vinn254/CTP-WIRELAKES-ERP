<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentValue extends Model
{
    public $timestamps = false;

    protected $fillable = ['entry_id', 'field_id', 'text_value', 'image_path'];
}
