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
 * My library — the reading the Academic Registrar has put up for this student's own programme. A title says who it was
 * written for by the course it is filed under, and that course is the lock: an ICT trainee opens ICT titles and the
 * reading filed for every course, nothing else. The office places a student on a course; a title of another programme
 * is not borrowed by typing its url.
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

        $kind = $request->input('kind');
        $kind = isset(LibraryItem::KINDS[$kind]) ? $kind : null;

        // The shelf a student is written for: the classes they sit in, and the reading the Registrar filed for every
        // course. Another programme's titles are not listed and not openable — an ICT student reads ICT.
        $courses = MemberPortal::readings($ctp);

        $course = $request->input('training_id');
        $course = $course === 'general' ? 'general'
            : (ctype_digit((string) $course) && $courses->contains('id', (int) $course) ? (int) $course : null);

        $items = Library::shelf($courses->pluck('id')->all())->with('course:id,name')
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

    /** A single title, opened by the trainees it was filed for. Another programme's reading is not theirs. */
    public function open(Request $request, LibraryItem $item)
    {
        $ctp = $this->ctp();

        abort_unless($item->status === 'on', 404, 'That reading is off the shelf.');
        $this->reads($item, $ctp);

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
        $this->reads($item, $ctp);
        abort_unless(Library::charges($request->user()), 422,
            'You are not charged for library reading.');

        $purchase = Library::request($request->user(), $ctp, $item);

        return redirect()->route('portal.library')->with('status',
            'Noted — the Academic Registrar will confirm the '.Library::priceLabel($purchase->amount)
            .' for '.$item->title.'. Your download opens as soon as they do.');
    }

    /** A title filed for another programme cannot be reached by typing its url either. */
    private function reads(LibraryItem $item, CtpRegistration $ctp): void
    {
        abort_unless(Library::reads($item, MemberPortal::readings($ctp)->pluck('id')->all()), 403,
            $item->title.' is filed under '.$item->courseLabel().', a programme you are not on. '
            .'Ask the programme office to place you on it — reading filed for every course is open to you already.');
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
