<?php

namespace App\Models;

use App\Support\Uploads;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class ContentField extends Model
{
    public $timestamps = false;

    protected $fillable = ['section_id', 'key', 'label', 'type', 'sort_order'];

    public function section()
    {
        return $this->belongsTo(ContentSection::class, 'section_id');
    }

    /** Unique, readable filename for an uploaded image value. The extension comes from the bytes, not the name. */
    public static function slugName(self $field, UploadedFile $file): string
    {
        $base = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'image';

        return $base.'-f'.$field->id.'-'.Str::random(6).'.'.Uploads::imageExtension($file);
    }
}
