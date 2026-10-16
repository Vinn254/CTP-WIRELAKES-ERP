<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\Certification;
use App\Models\Cohort;
use App\Models\Community;
use App\Models\CtpRegistration;
use App\Models\Enrollment;
use App\Models\EWasteCollection;
use App\Models\FinanceEntry;
use App\Models\FundiRegistration;
use App\Models\HirerRegistration;
use App\Models\InventoryItem;
use App\Models\Need;
use App\Models\Payout;
use App\Models\Project;
use App\Models\ProjectMatch;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\Trainer;
use App\Models\WorkOrder;
use App\Models\WorkOrderDispute;
use App\Models\WorkOrderMilestone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Module 12 §15.6 — who did what, when. Only the records a decision changes are watched, and only the fields that
 * carry a decision are written out, so the trail says what happened without copying the file store into a log.
 */
class Audit
{
    public const WATCHED = [
        CtpRegistration::class, FundiRegistration::class, HirerRegistration::class,
        Community::class, Project::class, Need::class, Cohort::class,
        ProjectMatch::class, Enrollment::class, Trainer::class, Certification::class, EWasteCollection::class,
        Payout::class, PurchaseRequest::class, PurchaseOrder::class, FinanceEntry::class,
        InventoryItem::class, StockMovement::class, Setting::class,
        WorkOrder::class, WorkOrderMilestone::class, WorkOrderDispute::class,
    ];

    /** @var array<string, bool> */
    private static array $readable = [];

    /** Fields worth naming in the trail, because a person or an executive changed them. */
    private const NOTABLE = [
        'status', 'state', 'stage', 'priority', 'tier', 'availability', 'fee_status', 'is_listed', 'active',
        'decision_note', 'rejection_reason', 'held_reason', 'net_ksh', 'agreed_amount_ksh', 'amount_ksh',
        'qty_on_hand', 'reason', 'value', 'supplied_on',
        'schedule', 'outcome', 'penalty_ksh', 'percent_complete',
    ];

    /** Never written into the trail, whatever changes. */
    private const SECRET = [
        'password', 'remember_token', 'token', 'api_token', 'photo_path', 'photo_name', 'photo_size',
        'id_scan_path', 'passport_photo_path', 'business_cert_path', 'trade_cert_paths', 'portfolio_paths',
        'certificate_path', 'org_doc_path', 'logo_path',
        'confirmation_path', 'confirmation_name', 'issue_path', 'issue_name', 'issue_size',
    ];

    public static function record(Model $subject, string $action, ?string $detail = null): void
    {
        if (! static::tableExists($subject)) {
            return;
        }

        $user = auth()->user();

        ActivityLog::create([
            'user_id' => $user?->id,
            'actor_name' => $user?->name ?? 'the system',
            'action' => $action,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'subject_label' => Str::limit(static::label($subject), 190, ''),
            'detail' => Str::limit($detail ?? static::changes($subject, $action), 1000, ''),
            'ip' => request()?->ip(),
        ]);
    }

    /** The name a reader recognises, taken from whatever the record is called by. */
    public static function label(Model $subject): string
    {
        if ($subject instanceof Setting) {
            return $subject->label();
        }

        if ($subject instanceof ProjectMatch) {
            return 'round '.$subject->round.' — '.($subject->fundi->full_name ?? 'an E-Fundi')
                .' for '.($subject->project->title ?? 'a project');
        }

        if ($subject instanceof WorkOrderDispute) {
            return 'dispute on '.($subject->workOrder->reference ?? 'a work order')
                .($subject->outcome ? ' — '.$subject->outcomeLabel() : '');
        }

        if ($subject instanceof WorkOrderMilestone) {
            return $subject->name.' — '.($subject->workOrder->reference ?? 'a work order');
        }

        foreach (['full_name', 'name', 'title', 'organisation', 'reference', 'item_name', 'sku'] as $field) {
            if (filled($subject->getAttribute($field))) {
                return (string) $subject->getAttribute($field);
            }
        }

        return class_basename($subject::class).' #'.$subject->getKey();
    }

    private static function changes(Model $subject, string $action): string
    {
        if ($action !== 'updated') {
            return $action === 'created' ? 'new record' : '';
        }

        $dirty = collect($subject->getChanges())
            ->except(['updated_at', 'created_at'])
            ->except(self::SECRET);

        $notable = $dirty->filter(fn ($v, $key) => in_array($key, self::NOTABLE, true))
            ->map(fn ($v, $key) => $key.': '.(is_scalar($v) ? Str::limit((string) $v, 60, '') : 'set'))
            ->values();

        $other = $dirty->count() - $notable->count();

        if ($other > 0) {
            $notable->push('and '.$other.' other '.Str::plural('field', $other));
        }

        return $notable->isEmpty() ? 'details updated' : $notable->join(', ');
    }

    /** The observers are registered for the whole app, including the moment migrations create the table. */
    private static function tableExists(Model $subject): bool
    {
        $name = (new ActivityLog)->getTable();

        if (isset(self::$readable[$name])) {
            return self::$readable[$name];
        }

        try {
            self::$readable[$name] = Schema::connection($subject->getConnectionName())->hasTable($name);
        } catch (\Throwable) {
            self::$readable[$name] = false;
        }

        return self::$readable[$name];
    }
}
