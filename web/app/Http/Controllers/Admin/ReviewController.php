<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountInvite;
use App\Models\CtpRegistration;
use App\Models\FundiRegistration;
use App\Models\HirerRegistration;
use App\Models\Project;
use App\Models\ProjectApplication;
use App\Support\ReviewActions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ReviewController extends Controller
{
    public const TYPES = [
        'ctp' => ['model' => CtpRegistration::class, 'label' => 'CTP Applications', 'code' => 'ctp_code'],
        'fundi' => ['model' => FundiRegistration::class, 'label' => 'Fundi Applications', 'code' => 'ef_code'],
        'hirer' => ['model' => HirerRegistration::class, 'label' => 'Hirer Applications', 'code' => 'hirer_code'],
    ];

    /** Whitelisted document slots per type; numeric indexes allowed for json arrays. */
    public const DOCS = [
        'ctp' => ['id_scan' => 'id_scan_path', 'certificate' => 'certificate_path', 'passport' => 'passport_photo_path'],
        'fundi' => [
            'passport_photo' => 'passport_photo_path',
            'id_scan' => 'id_scan_path', 'business_cert' => 'business_cert_path',
            'trade_cert' => 'trade_cert_paths', 'portfolio' => 'portfolio_paths',
        ],
        'hirer' => ['org_doc' => 'org_doc_path'],
    ];

    private function model(string $type): string
    {
        abort_unless(isset(self::TYPES[$type]), 404);

        return self::TYPES[$type]['model'];
    }

    public function index(Request $request, string $type)
    {
        $model = $this->model($type);
        $statuses = match ($type) {
            'ctp' => array_keys(CtpRegistration::STATUSES),
            'fundi' => array_keys(FundiRegistration::STATUSES),
            default => ['pending_review', 'active', 'rejected'],
        };
        $status = $request->query('status', 'pending_review');
        abort_unless(in_array($status, [...$statuses, 'all'], true), 404);

        $records = $model::query()
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.registrations.index', [
            'type' => $type,
            'label' => self::TYPES[$type]['label'],
            'status' => $status,
            'records' => $records,
            'assigned' => ProjectApplication::where('applicant_type', $type)
                ->whereIn('applicant_id', $records->pluck('id'))
                ->with('project:id,reference,title')
                ->orderBy('created_at')
                ->get()
                ->groupBy('applicant_id'),
            'counts' => $model::selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status'),
            'pendingByType' => collect(['ctp', 'fundi'])
                ->mapWithKeys(fn ($t) => [$t => self::TYPES[$t]['model']::where('status', 'pending_review')->count()]),
        ]);
    }

    public function show(string $type, int $id)
    {
        $record = $this->model($type)::findOrFail($id);

        return view('admin.registrations.show', [
            'type' => $type,
            'label' => self::TYPES[$type]['label'],
            'record' => $record,
            'docs' => $this->documentList($type, $record),
            'invite' => in_array($type, ['ctp', 'fundi'], true)
                ? AccountInvite::where('member_type', $type)->where('member_id', $id)->first()
                : null,
        ]);
    }

    public function approve(string $type, int $id)
    {
        $record = $this->model($type)::findOrFail($id);

        return back()->with('status', ReviewActions::approve(
            $record, self::TYPES[$type]['code'], in_array($type, ['ctp', 'fundi'], true)
        ));
    }

    /** Record a membership fee the applicant paid by M-Pesa bill, bank transfer or cash. */
    public function markFee(Request $request, string $type, int $id)
    {
        abort_unless(in_array($type, ['ctp', 'fundi'], true), 404, 'Only CTP and E-Fundi applications pay a membership fee.');
        $model = $this->model($type);

        return back()->with('status', ReviewActions::recordFee(
            $model::findOrFail($id), ReviewActions::feeFrom($request)
        ));
    }

    public function reject(Request $request, string $type, int $id)
    {
        $record = $this->model($type)::findOrFail($id);
        $data = $request->validate(['rejection_reason' => 'required|string|max:2000']);
        ReviewActions::reject($record, $data['rejection_reason']);

        return back()->with('status', 'Application rejected; the reason will be shared with the applicant.');
    }

    /** Replace a stale activation link for an approved member. */
    public function reissue(string $type, int $id)
    {
        abort_unless(in_array($type, ['ctp', 'fundi'], true), 404);

        return back()->with('status', ReviewActions::reissue($this->model($type)::findOrFail($id)));
    }

    /** Move a CTP who finished the programme into the fundi pool. */
    public function setTier(Request $request, string $type, int $id)
    {
        abort_unless($type === 'ctp', 404);
        $record = CtpRegistration::findOrFail($id);

        $data = $request->validate(['fundi_tier' => 'nullable|in:'.implode(',', array_keys(Project::CTP_POOL_TIERS))]);
        ReviewActions::setPoolTier($record, $data['fundi_tier'] ?? null);

        return back()->with('status', $record->full_name.' can now see '
            .($record->fundi_tier ? Str::headline($record->fundi_tier).' projects' : 'only CTP attachment projects').'.');
    }

    public function file(string $type, int $id, string $doc, Request $request)
    {
        $record = $this->model($type)::findOrFail($id);
        $path = $this->resolvePath($type, $record, $doc);
        abort_unless($path && Storage::disk('local')->exists($path), 404, 'Document not uploaded.');

        if ($request->boolean('download')) {
            return Storage::disk('local')->response($path);
        }

        return response()->file(Storage::disk('local')->path($path));
    }

    private function documentList(string $type, $record): array
    {
        $list = [];
        foreach (self::DOCS[$type] as $slot => $column) {
            $value = $record->{$column};
            if (is_array($value)) {
                foreach ($value as $i => $path) {
                    $list["{$slot}.{$i}"] = $path;
                }
            } elseif ($value) {
                $list[$slot] = $value;
            }
        }

        return $list;
    }

    private function resolvePath(string $type, $record, string $doc): ?string
    {
        [$slot, $index] = array_pad(explode('.', $doc, 2), 2, null);
        abort_unless(isset(self::DOCS[$type][$slot]), 404);

        $value = $record->{self::DOCS[$type][$slot]};

        return is_array($value)
            ? ($value[(int) $index] ?? null)
            : ($index === null ? $value : null);
    }
}
