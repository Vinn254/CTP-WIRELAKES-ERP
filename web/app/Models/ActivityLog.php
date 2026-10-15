<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Module 12 §15.6 — one line saying that something happened in the programme, on whose account, and what it was.
 * The trail keeps the name of the person even if their account is later closed, and it is never edited or deleted:
 * a wrong entry is answered by the next entry.
 */
class ActivityLog extends Model
{
    protected $table = 'activity_log';

    protected $fillable = [
        'user_id', 'actor_name', 'action', 'subject_type', 'subject_id', 'subject_label', 'detail', 'ip',
    ];

    public const ACTIONS = [
        'created' => 'Opened',
        'updated' => 'Changed',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'revoked' => 'Revoked',
        'released' => 'Released',
        'deleted' => 'Closed off',
    ];

    /** What a reader calls each kind of record, on the trail and in its filter. */
    public const RECORD_NAMES = [
        CtpRegistration::class => 'CTP application',
        FundiRegistration::class => 'E-Fundi application',
        HirerRegistration::class => 'Hirer application',
        Community::class => 'Community',
        Project::class => 'Project',
        Need::class => 'Community need',
        Cohort::class => 'Cohort',
        Enrollment::class => 'Seat on a cohort',
        Trainer::class => 'Fundi-trainer',
        Certification::class => 'Certificate',
        EWasteCollection::class => 'E-waste collection',
        Payout::class => 'Payout',
        PurchaseRequest::class => 'Purchase request',
        PurchaseOrder::class => 'Purchase order',
        FinanceEntry::class => 'Money register line',
        InventoryItem::class => 'Stock card',
        StockMovement::class => 'Goods in or out',
        Setting::class => 'Programme setting',
    ];

    public static function recordName(?string $class): string
    {
        return self::RECORD_NAMES[$class] ?? str(class_basename((string) $class))->headline()->toString();
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withDefault();
    }

    public function actionLabel(): string
    {
        return self::ACTIONS[$this->action] ?? ucfirst($this->action);
    }

    public function subjectName(): string
    {
        if (! $this->subject_type) {
            return $this->detail ?: 'the programme';
        }

        return static::recordName($this->subject_type).($this->subject_label ? ' — '.$this->subject_label : ' #'.$this->subject_id);
    }

    /** Who did it: the account if it still exists, otherwise the name kept on the line. */
    public function whoLabel(): string
    {
        return $this->actor->exists ? $this->actor->name : ($this->actor_name ?: 'the system');
    }

    public function whenLabel(): string
    {
        return $this->created_at ? $this->created_at->format('d M Y H:i') : '—';
    }
}
