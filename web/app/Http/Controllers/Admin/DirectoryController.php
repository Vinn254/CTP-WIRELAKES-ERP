<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StaffRecord;
use App\Support\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * The staff directory: everyone who works in the organisation, with the file Human Resources keeps on them —
 * next of kin, the C14 on record and what they are paid. The list is written from the accounts and the onboarding
 * desk, never typed by hand, so nobody working in the programme can be missing from it.
 */
class DirectoryController extends Controller
{
    public function index(Request $request)
    {
        $this->gate();
        Staff::sync();

        $filter = $request->query('f');
        $records = StaffRecord::with(['user:id,name,email,role,active,departments,roles', 'application.opening:id,reference,title'])
            ->when($filter === 'incomplete', fn ($q) => $q->where(fn ($s) => $s->whereNull('next_of_kin_name')
                ->orWhereNull('salary')->orWhereNull('started_on')->orWhereNull('id_number')))
            ->when($filter === 'waiting', fn ($q) => $q->whereNull('user_id'))
            ->when($filter === 'c14', fn ($q) => $q->whereNull('c14_path'))
            ->orderByRaw('case when user_id is null then 1 else 0 end')
            ->orderBy('name')->get();

        return view('admin.staff.directory', [
            'records' => $records,
            'filter' => in_array($filter, ['incomplete', 'waiting', 'c14'], true) ? $filter : 'all',
            'all' => StaffRecord::count(),
            'withAccount' => StaffRecord::whereNotNull('user_id')->count(),
            'incomplete' => StaffRecord::where(fn ($q) => $q->whereNull('next_of_kin_name')->orWhereNull('salary')
                ->orWhereNull('started_on')->orWhereNull('id_number'))->count(),
            'noC14' => StaffRecord::whereNull('c14_path')->count(),
        ]);
    }

    public function show(StaffRecord $record)
    {
        $this->gate();

        return view('admin.staff.record', [
            'record' => $record->load(['user', 'application.opening', 'recorder:id,name']),
            'liveDepartments' => \App\Support\Departments::live(),
        ]);
    }

    /** Human Resources keeps the file up to date. A new C14 replaces the one on record, it never adds a second. */
    public function update(Request $request, StaffRecord $record)
    {
        $this->gate();

        return back()->with('status', Staff::amend($record, $request, $request->user()));
    }

    /** A C14 names other people and their contacts: it is served to this desk, and to nobody else. */
    public function c14(StaffRecord $record)
    {
        $this->gate();
        abort_unless($record->hasC14(), 404, 'That file is no longer on record.');

        return Storage::disk('local')->response($record->c14_path, $record->c14_name ?: 'C14');
    }

    /** The face on the file — the same rule as the C14: this desk sees it, nobody else. */
    public function photo(StaffRecord $record)
    {
        $this->gate();
        abort_unless($record->hasPhoto(), 404, 'That photo is no longer on record.');

        return Storage::disk('local')->response($record->photo_path);
    }

    private function gate(): void
    {
        abort_unless(Staff::decides(auth()->user()), 403,
            'The staff directory is a Human Resources record — next of kin, pay and the C14 stay on this desk.');
    }
}
