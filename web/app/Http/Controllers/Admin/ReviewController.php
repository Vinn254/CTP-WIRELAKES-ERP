<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountInvite;
use App\Models\CtpRegistration;
use App\Models\FundiRegistration;
use App\Models\HirerRegistration;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        $status = $request->query('status', 'pending_review');
        abort_unless(in_array($status, ['pending_review', 'active', 'rejected', 'all'], true), 404);

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
        $model = $this->model($type);
        $record = $model::findOrFail($id);
        abort_unless($record->status === 'pending_review', 422, 'Only pending applications can be approved.');

        $codeColumn = self::TYPES[$type]['code'];
        DB::transaction(function () use ($model, $record, $codeColumn, $type) {
            $record->status = 'active';
            $record->{$codeColumn} = $model::nextCode();
            $record->save();

            if (in_array($type, ['ctp', 'fundi'], true)) {
                AccountInvite::issueFor($record, auth()->id());
            }
        });

        return back()->with('status', in_array($type, ['ctp', 'fundi'], true)
            ? "Approved — {$record->{$codeColumn}} issued. Copy the activation link below and send it so they can set a password."
            : "Approved — {$record->{$codeColumn}} issued.");
    }

    public function reject(Request $request, string $type, int $id)
    {
        $record = $this->model($type)::findOrFail($id);
        abort_unless($record->status === 'pending_review', 422, 'Only pending applications can be rejected.');

        $data = $request->validate(['rejection_reason' => 'required|string|max:2000']);
        $record->update(['status' => 'rejected', 'rejection_reason' => $data['rejection_reason']]);

        return back()->with('status', 'Application rejected; the reason will be shared with the applicant.');
    }

    /** Replace a stale activation link for an approved member. */
    public function reissue(string $type, int $id)
    {
        abort_unless(in_array($type, ['ctp', 'fundi'], true), 404);
        $record = $this->model($type)::findOrFail($id);
        abort_unless($record->status === 'active', 422, 'Only approved members have activation links.');

        AccountInvite::issueFor($record, auth()->id(), true);

        return back()->with('status', 'New activation link ready — copy it below and send it.');
    }

    /** Move a CTP who finished the programme into the fundi pool. */
    public function setTier(Request $request, string $type, int $id)
    {
        abort_unless($type === 'ctp', 404);
        $record = CtpRegistration::findOrFail($id);

        $data = $request->validate(['fundi_tier' => 'nullable|in:'.implode(',', array_keys(Project::CTP_POOL_TIERS))]);
        $record->update(['fundi_tier' => $data['fundi_tier'] ?? null]);

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
