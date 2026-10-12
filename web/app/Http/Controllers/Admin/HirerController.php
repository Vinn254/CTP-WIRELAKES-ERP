<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\County;
use App\Models\HirerRegistration;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Hirers are recorded by staff — organisations no longer post projects themselves.
 * Staff capture the deal here, then post the projects against that organisation.
 */
class HirerController extends Controller
{
    public function index(Request $request)
    {
        $hirers = HirerRegistration::withCount('projects')
            ->when($request->query('q'), fn ($q, $term) => $q->where('organisation', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%'))
            ->orderBy('organisation')
            ->paginate(20)
            ->withQueryString();

        return view('admin.hirers.index', [
            'hirers' => $hirers,
            'counties' => County::orderBy('name')->pluck('name'),
            'orgTypes' => HirerRegistration::ORG_TYPES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateHirer($request);

        $hirer = HirerRegistration::create($data + [
            'reference' => 'HIR-'.strtoupper(substr(bin2hex(random_bytes(3)), 0, 6)),
            'status' => 'active',
            'hirer_code' => HirerRegistration::nextCode(),
        ]);

        return back()->with('status', $hirer->organisation.' added as '.$hirer->hirer_code.'.');
    }

    public function update(Request $request, HirerRegistration $hirer)
    {
        $hirer->update($this->validateHirer($request, $hirer->id));

        return back()->with('status', $hirer->organisation.' updated.');
    }

    public function toggle(HirerRegistration $hirer)
    {
        $active = $hirer->status === 'active';
        $hirer->update(['status' => $active ? 'rejected' : 'active']);

        return back()->with('status', $hirer->organisation.($active ? ' deactivated.' : ' reactivated.'));
    }

    public function destroy(HirerRegistration $hirer)
    {
        if (Project::where('hirer_id', $hirer->id)->exists()) {
            return back()->with('error', 'This organisation still has projects posted against it — delete those first.');
        }

        $name = $hirer->organisation;
        $hirer->delete();

        return back()->with('status', $name.' removed.');
    }

    private function validateHirer(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'organisation' => 'required|string|max:120',
            'contact_name' => 'required|string|max:120',
            'email' => ['nullable', 'email', 'max:120', Rule::unique('hirer_registrations', 'email')->ignore($ignoreId)],
            'phone' => ['nullable', 'max:20', Rule::unique('hirer_registrations', 'phone')->ignore($ignoreId)],
            'county' => 'required|string|max:80',
            'sub_county' => 'required|string|max:100',
            'ward' => 'required|string|max:100',
            'org_type' => 'required|in:'.implode(',', array_keys(HirerRegistration::ORG_TYPES)),
        ]);
    }
}
