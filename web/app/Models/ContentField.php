<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ContentField extends Model
{
    public $timestamps = false;

    protected $fillable = ['section_id', 'key', 'label', 'type', 'sort_order'];

    public function section()
    {
        return $this->belongsTo(ContentSection::class, 'section_id');
    }

    /** Unique, readable filename for an uploaded image value. */
    public static function slugName(self $field, string $original): string
    {
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION)) ?: 'png';
        $base = Str::slug(pathinfo($original, PATHINFO_FILENAME)) ?: 'image';

        return $base.'-f'.$field->id.'-'.Str::random(6).'.'.$ext;
    }
}
