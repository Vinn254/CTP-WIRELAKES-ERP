<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LibraryItem;
use App\Models\Training;
use App\Support\Documents;
use App\Support\TeachingDesk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The Academic Registrar's library. Books, notes, slides and any other reading the students have to work through are
 * posted here, filed against a course so every trainee taking that training reads the same copy.
 *
 * The Training department owns the route, so a login without that module never reaches it — and a title is taken off
 * the shelf rather than deleted, so what a class was told to read stays on record.
 */
class LibraryController extends Controller
{
    public function index(Request $request)
    {
        $this->guard();

        $course = $request->input('training_id');
        $kind = $request->input('kind');
        $shelf = $request->input('status');

        $course = $course === 'general' ? 'general' : (ctype_digit((string) $course) ? (int) $course : null);
        $kind = isset(LibraryItem::KINDS[$kind]) ? $kind : null;
        $shelf = isset(LibraryItem::SHELF[$shelf]) ? $shelf : null;

        $rows = LibraryItem::with(['course:id,name', 'poster:id,name'])
            ->when($kind, fn ($q) => $q->where('kind', $kind))
            ->when($shelf, fn ($q) => $q->where('status', $shelf))
            ->latest('id')->get();

        $courses = Training::active()->get(['id', 'name']);

        $shown = match (true) {
            $course === 'general' => $rows->whereNull('training_id'),
            is_int($course) => $rows->where('training_id', $course),
            default => $rows,
        };

        // The shelf is read programme by programme: the shared reading first, then one block per course with titles on it.
        $groups = $shown->groupBy(fn (LibraryItem $i) => $i->courseLabel())
            ->map(fn ($block) => ['label' => $block->first()->courseLabel(), 'items' => $block->values()])
            ->sortBy([
                fn (array $g) => $g['label'] === 'Every course' ? 0 : 1,
                fn (array $g) => $g['label'],
            ])
            ->values();

        $all = LibraryItem::all();

        return view('admin.library.index', [
            'groups' => $groups,
            'items' => $shown,
            'tabs' => collect([['key' => 'all', 'label' => 'Every programme', 'count' => $rows->count()],
                ['key' => 'general', 'label' => 'Every course', 'count' => $rows->whereNull('training_id')->count()]])
                ->concat($courses->map(fn ($c) => ['key' => (string) $c->id, 'label' => $c->name,
                    'count' => $rows->where('training_id', $c->id)->count()]))
                ->all(),
            'courses' => $courses,
            'kinds' => LibraryItem::KINDS,
            'shelfOptions' => LibraryItem::SHELF,
            'course' => $course,
            'kind' => $kind,
            'shelf' => $shelf,
            'stats' => [
                ['On the shelf', $all->where('status', 'on')->count()],
                ['Off the shelf', $all->where('status', 'off')->count()],
                ['For every course', $all->whereNull('training_id')->count()],
                ['Courses covered', $all->whereNotNull('training_id')->pluck('training_id')->unique()->count()],
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->guard();

        $data = LibraryItem::dataFrom($request);

        $item = LibraryItem::create([
            'title' => $data['title'],
            'kind' => $data['kind'],
            'training_id' => $data['training_id'] ?? null,
            'note' => $data['note'] ?? null,
            'file_path' => $request->file('file')->store($this->folder($data['title']), 'local'),
            'file_name' => $request->file('file')->getClientOriginalName(),
            'file_size' => $request->file('file')->getSize(),
            'status' => 'on',
            'posted_by_user' => $request->user()->id,
            'posted_at' => now(),
        ]);

        return redirect()->route('admin.library.index')->with('status',
            $item->title.' is on the shelf for '.$item->courseLabel().'.');
    }

    /** Correcting a title overwrites it — the file the students open becomes the one saved now. */
    public function update(Request $request, LibraryItem $item)
    {
        $this->guard();

        $data = LibraryItem::dataFrom($request, false);

        $changes = collect($data)->except('file')->filter(fn ($value) => $value !== null)->all();

        // The same sheet carries the shelf switch, so putting a title back on or off is saved with the correction.
        if (array_key_exists($request->input('status'), LibraryItem::SHELF)) {
            $changes['status'] = $request->input('status');
        }

        if ($request->hasFile('file')) {
            $store = Storage::disk('local');
            if ($store->exists($item->file_path)) {
                $store->delete($item->file_path);
            }

            $changes['file_path'] = $request->file('file')->store($this->folder($data['title'] ?? $item->title), 'local');
            $changes['file_name'] = $request->file('file')->getClientOriginalName();
            $changes['file_size'] = $request->file('file')->getSize();
        }

        $item->update($changes);

        return back()->with('status', $item->title.' has been put right — the students now open this file.');
    }

    /** Putting a title on or off the shelf only ever changes that one field; the file stays on record. */
    public function shelf(Request $request, LibraryItem $item, string $status)
    {
        $this->guard();
        abort_unless(array_key_exists($status, LibraryItem::SHELF), 404);

        $item->update(['status' => $status]);

        return back()->with('status', $item->title.' is '.$item->shelfLabel().'.');
    }

    public function open(Request $request, LibraryItem $item)
    {
        $this->guard();

        return Documents::file($request, $item->file_path, $item->file_name ?: $item->title);
    }

    /** The reading files are filed under the title's own folder on the private disk. */
    private function folder(string $title): string
    {
        return 'library/'.Str::slug($title);
    }

    private function guard(): void
    {
        abort_unless(TeachingDesk::approves(auth()->user()), 403,
            'The Academic Registrar keeps the library, at Training & Certification.');
    }
}
