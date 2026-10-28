<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * The programme switches the code actually reads (§15.6). A key that no code looks at is not offered here:
     * a setting nothing uses would be worse than no setting, because it looks like it works.
     */
    public const EDITABLE = [
        'membership_fee_ctp' => ['label' => 'CTP payment recorded by default (KSh)', 'type' => 'money',
            'read' => 'The amount pre-filled when the office records money received on a CTP application. No application waits on it.'],
        'membership_fee_fundi' => ['label' => 'E-Fundi payment recorded by default (KSh)', 'type' => 'money',
            'read' => 'The amount pre-filled when the office records money received on an E-Fundi application. No application waits on it.'],
        'programme_email' => ['label' => 'Programme email', 'type' => 'text',
            'read' => 'Shown on the public pages and quoted on member letters.'],
        'programme_phone' => ['label' => 'Programme phone', 'type' => 'text',
            'read' => 'Shown on the public pages and on the registration forms.'],
        'paybill_number' => ['label' => 'M-Pesa Paybill', 'type' => 'text',
            'read' => 'Printed on every invoice and shown to a member who owes money.'],
        'paybill_account' => ['label' => 'Paybill account number', 'type' => 'text',
            'read' => 'The account number a payer puts after the Paybill above.'],
    ];

    public static function get(string $key, ?string $default = null): ?string
    {
        return static::where('key', $key)->value('value') ?? $default;
    }

    public static function set(string $key, string $value): self
    {
        return static::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public function label(): string
    {
        return self::EDITABLE[$this->key]['label'] ?? $this->key;
    }
}

