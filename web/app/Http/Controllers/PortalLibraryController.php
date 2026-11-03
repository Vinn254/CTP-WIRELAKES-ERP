<?php

namespace App\Http\Controllers;

use App\Models\CtpRegistration;
use App\Models\LibraryItem;
use App\Support\Documents;
use App\Support\Finance;
use App\Support\Library;
use App\Support\MemberPortal;
use Illuminate\Http\Request;

/**
 * My library — the reading the Academic Registrar has put up. Everything on the shelf is open to a student: the
 * course a title is filed under says who it was written for, it is not a lock, so a trainee not yet on a class roll
 * still reads what the Registrar posted for their programme.
 *
 * Reading happens on the platform and costs nothing. Saving a title to the phone is what costs — KSh 50 for a past
 * paper, KSh 100 for a book or notes, KSh 500 for a syllabus, sent to the programme's Paybill — and the download
 * stays shut until the Registrar confirms the money. The gate is applied here as well as on the page, so a student
 * cannot reach the file by typing the save url.
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
            'charges' => Library::charges($request->user()),
            'kept' => Library::kept($ctp),
            'paybill' => Finance::paybill(),
        ]);
    }

    /** A single title, opened by any trainee — the shelf itself is what decides what students may read. */
    public function open(Request $request, LibraryItem $item)
    {
        $ctp = $this->ctp();

        abort_unless($item->status === 'on', 404, 'That reading is off the shelf.');

        if ($request->boolean('download') && ! Library::entitled($ctp, $item, $request->user())) {
            return redirect()->route('portal.library')
                ->with('error', Library::priceLabel(Library::price($item)).' — '.$item->title
                    .' is saved to your phone only after the Academic Registrar confirms the payment.');
        }

        return Documents::file($request, $item->file_path, $item->file_name ?: $item->title);
    }

    /** The student says the money has gone in. The Registrar confirms it, and the download opens then. */
    public function pay(Request $request, LibraryItem $item)
    {
        $ctp = $this->ctp();

        abort_unless($item->status === 'on', 404, 'That reading is off the shelf.');
        abort_unless(Library::charges($request->user()), 422,
            'You are not charged for library reading.');

        $purchase = Library::request($request->user(), $ctp, $item);

        return redirect()->route('portal.library')->with('status',
            'Noted — the Academic Registrar will confirm the '.Library::priceLabel($purchase->amount)
            .' for '.$item->title.'. Your download opens as soon as they do.');
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
