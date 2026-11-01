<?php

namespace App\Http\Controllers;

use App\Models\CtpRegistration;
use App\Models\LibraryItem;
use App\Support\Documents;
use App\Support\MemberPortal;
use Illuminate\Http\Request;

/**
 * My library — the reading the Academic Registrar has put up. Everything on the shelf is open to a student: the
 * course a title is filed under says who it was written for, it is not a lock, so a trainee not yet on a class roll
 * still reads what the Registrar posted for their programme. Titles are read on the screen or saved to the phone;
 * the file itself sits on the private disk and only ever opens through a signed-in trainee.
 */
class PortalLibraryController extends Controller
{
    public function index(Request $request)
    {
        $ctp = $this->ctp();
        $mine = $this->courses($ctp);

        $kind = $request->input('kind');
        $kind = isset(LibraryItem::KINDS[$kind]) ? $kind : null;

        // The filter offers the student's own programmes first, then any other programme that has reading on the shelf.
        $courses = $mine->concat(LibraryItem::onShelf()->whereNotNull('training_id')
            ->with('course:id,name')->get()->pluck('course')->filter()->unique('id')->values())->unique('id')->values();

        $course = $request->input('training_id');
        $course = $course === 'general' ? 'general'
            : (ctype_digit((string) $course) && $courses->contains('id', (int) $course) ? (int) $course : null);

        $items = LibraryItem::onShelf()->with('course:id,name')
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

    /** A single title, opened by any trainee — the shelf itself is what decides what students may read. */
    public function open(Request $request, LibraryItem $item)
    {
        $this->ctp();

        abort_unless($item->status === 'on', 404, 'That reading is off the shelf.');

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
