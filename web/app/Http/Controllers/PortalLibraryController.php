<?php

namespace App\Http\Controllers;

use App\Models\CtpRegistration;
use App\Models\LibraryItem;
use App\Support\Documents;
use App\Support\MemberPortal;
use Illuminate\Http\Request;

/**
 * My library — the reading the Academic Registrar has put up for a trainee's course, plus anything the Registrar filed
 * for every student. Titles are read on the screen or saved to the phone; the file itself sits on the private disk and
 * only ever opens through a row this student is entitled to.
 */
class PortalLibraryController extends Controller
{
    public function index(Request $request)
    {
        $ctp = $this->ctp();
        $courses = $this->courses($ctp);

        $kind = $request->input('kind');
        $kind = isset(LibraryItem::KINDS[$kind]) ? $kind : null;

        // A student only ever filters within the courses they actually sit, so anything else falls back to the full list.
        $course = $request->input('training_id');
        $course = $course === 'general' ? 'general'
            : (ctype_digit((string) $course) && $courses->contains('id', (int) $course) ? (int) $course : null);

        $items = LibraryItem::onShelf()->with('course:id,name')
            ->when($courses->isNotEmpty(), fn ($q) => $q->where(fn ($w) => $w
                ->whereNull('training_id')->orWhereIn('training_id', $courses->pluck('id'))))
            ->when($courses->isEmpty(), fn ($q) => $q->whereNull('training_id'))
            ->when($course === 'general', fn ($q) => $q->whereNull('training_id'))
            ->when(is_int($course), fn ($q) => $q->where('training_id', $course))
            ->when($kind, fn ($q) => $q->where('kind', $kind))
            ->latest('id')->get();

        return view('portal.library', MemberPortal::board($ctp) + [
            'items' => $items,
            'courses' => $courses,
            'course' => $course,
            'kind' => $kind,
            'kinds' => LibraryItem::KINDS,
        ]);
    }

    /** A single title, opened only by a student whose course it is filed under. */
    public function open(Request $request, LibraryItem $item)
    {
        $ctp = $this->ctp();

        abort_unless($item->status === 'on', 404, 'That reading is off the shelf.');
        abort_unless($item->readsFor(null) || $this->courses($ctp)->contains('id', $item->training_id), 403,
            'That title is filed under another course.');

        return Documents::file($request, $item->file_path, $item->file_name ?: $item->title);
    }

    /** The trainings this CTP is actually sitting in class for — an application is not a class. */
    private function courses(CtpRegistration $ctp)
    {
        return $ctp->enrollments()->where('state', 'approved')
            ->with('cohort.training:id,name')->get()
            ->pluck('cohort.training')->filter()->unique('id')->values();
    }

    private function ctp(): CtpRegistration
    {
        $member = MemberPortal::resolve(auth()->user());

        abort_if(! $member, 403, 'Your member record could not be found. Contact the programme office.');
        abort_unless($member instanceof CtpRegistration, 403,
            'The library is a trainee\'s reading. An E-Fundi is given material by their trainer.');

        return $member;
    }
}
