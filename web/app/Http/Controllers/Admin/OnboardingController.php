<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JobApplication;
use App\Support\Departments;
use App\Support\Roles;
use App\Support\Staff;
use Illuminate\Http\Request;

/**
 * The onboarding desk. A person the careers board shortlisted and gave the job lands here, where Human Resources
 * writes their staff file — next of kin, the C14, pay and the module they will work in — and raises the
 * invitation that opens their login. From that moment they are on the staff directory.
 */
class OnboardingController extends Controller
{
    public function index()
    {
        $this->gate();

        return view('admin.staff.onboarding', [
            'waiting' => $this->applications(JobApplication::ONBOARDABLE),
            'done' => $this->applications(['onboarded'])->take(20),
            'liveDepartments' => Departments::live(),
            'roles' => Roles::all(),
        ]);
    }

    public function store(Request $request, JobApplication $application)
    {
        $this->gate();

        [$record, $message] = Staff::onboard($application, $request, $request->user());

        return redirect()->route('admin.directory.show', $record)->with('status', $message);
    }

    private function applications(array $statuses)
    {
        return JobApplication::with(['opening:id,reference,title,kind', 'reviewer:id,name',
            'onboarder:id,name', 'staffRecord:id,job_application_id,name'])
            ->whereIn('status', $statuses)->latest('id')->get();
    }

    private function gate(): void
    {
        abort_unless(Staff::decides(auth()->user()), 403,
            'A person is onboarded by Human Resources, not by the desk that posted the opening.');
    }
}
