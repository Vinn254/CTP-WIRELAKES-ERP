<?php

namespace App\Http\Controllers;

use App\Models\ProgrammeApplication;
use App\Support\OfficeAlert;
use App\Support\ProgrammeAreas;
use Illuminate\Http\Request;

/**
 * The six areas of work, each on its own public page: what the programme does there, explained with the office's own
 * pictures, and the one form a person fills in to ask for that work. Someone who wants to join or to work with the
 * programme is sent to the registration pages or to Careers instead — they are not made to apply through here.
 */
class AreaPageController extends Controller
{
    public function show(string $area)
    {
        abort_unless(ProgrammeAreas::find($area), 404);

        return view('pages.area', ProgrammeAreas::page($area));
    }

    /**
     * A request for the area's work. No account and no document is asked for — the office only needs to know what the
     * person wants done and how to reach them. Both sides are told: the person that the office has it and answers
     * within the promised window, the desk that a line is waiting with their contact details on it.
     */
    public function apply(Request $request, string $area)
    {
        abort_unless(ProgrammeAreas::find($area), 404);

        $application = new ProgrammeApplication(ProgrammeApplication::dataFrom($request, $area));
        $application->area = $area;
        $application->reference = ProgrammeApplication::nextReference();
        $application->status = 'received';
        $application->save();

        OfficeAlert::applicantAcknowledge($application);
        OfficeAlert::newApplication($application);

        return redirect(route($area).'#apply')
            ->with('status', 'Thank you, '.$application->name.'. Your request is with the programme office as '
                .$application->reference.'. We will give you feedback within '
                .ProgrammeApplication::TURNAROUND.' on the details you gave.');
    }
}
