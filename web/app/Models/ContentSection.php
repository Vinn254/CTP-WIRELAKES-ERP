<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentSection extends Model
{
    protected $fillable = ['page', 'key', 'name', 'kind', 'sort_order', 'is_visible'];

    public function fields()
    {
        return $this->hasMany(ContentField::class, 'section_id')->orderBy('sort_order');
    }

    public function entries()
    {
        return $this->hasMany(ContentEntry::class, 'section_id')->orderBy('sort_order');
    }
}
