<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentEntry extends Model
{
    public $timestamps = false;

    protected $fillable = ['section_id', 'sort_order'];

    public function section()
    {
        return $this->belongsTo(ContentSection::class, 'section_id');
    }

    public function values()
    {
        return $this->hasMany(ContentValue::class, 'entry_id');
    }
}
