<?php

namespace App\Models;

use App\Support\ProgrammeMail;
use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    protected $fillable = ['name', 'email', 'phone', 'topic', 'message'];

    /** Which desk the sender asked for, in the words the public site used. */
    public function topicLabel(): string
    {
        return ProgrammeMail::topics()[$this->topic] ?? 'General enquiry';
    }
}
