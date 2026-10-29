<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One CTP's tick on a day's attendance register. */
class CohortSessionMark extends Model
{
    protected $fillable = ['session_id', 'enrollment_id', 'present', 'note'];

    protected $casts = ['present' => 'boolean'];

    public function session(): BelongsTo
    {
        return $this->belongsTo(CohortSession::class, 'session_id');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }
}
