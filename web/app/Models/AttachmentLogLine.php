<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One day on an attachee's week: the clock times, what was done and the site supervisor's initials. Hours are worked
 * out from the two times by the desk that saves it, never typed.
 */
class AttachmentLogLine extends Model
{
    protected $table = 'attachment_log_lines';

    protected $fillable = [
        'attachment_log_id', 'log_date', 'time_in', 'time_out', 'task', 'hours', 'initials',
    ];

    protected $casts = [
        'log_date' => 'date',
        'hours' => 'float',
    ];

    public function log(): BelongsTo
    {
        return $this->belongsTo(AttachmentLog::class, 'attachment_log_id');
    }

    public function clockIn(): string
    {
        return $this->time_in ? substr($this->time_in, 0, 5) : '—';
    }

    public function clockOut(): string
    {
        return $this->time_out ? substr($this->time_out, 0, 5) : '—';
    }
}
