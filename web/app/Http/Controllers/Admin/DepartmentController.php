<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Trainer;
use App\Support\Departments;
use App\Support\Roles;
use App\Support\Workspace;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    /** Landing page after login. A super admin runs the programme by module; everyone else lands on their own desks. */
    public function index(Request $request)
    {
        $user = auth()->user();

        if ($user->isSuperAdmin()) {
            return view('admin.departments.index', [
                'departments' => Departments::visibleFor($user),
                'isSuper' => true,
                'teaching' => Trainer::forAccount($user)->first(),
            ]);
        }

        // Which title they are working as is remembered, so the sidebar keeps holding that desk's screens after the
        // first click. An empty choice clears it and puts the cards back.
        if ($request->has('role')) {
            $slug = (string) $request->input('role');
            $request->session()->put('workspace_role', in_array($slug, $user->roleSlugs(), true) ? $slug : null);
        }

        $acting = Workspace::acting($user);

        // A person holding two job titles chooses the one to work as before seeing any screen.
        if (! $acting && ($cards = Workspace::cards($user)) && count($cards) > 1) {
            return view('admin.workspace.roles', ['cards' => $cards]);
        }

        return view('admin.workspace.overview', [
            'groups' => Workspace::groups($user),
            'waiting' => Workspace::waiting($user),
            'roleNames' => Roles::namesFor($acting ? [$acting] : $user->roleSlugs()),
            'canChange' => $acting !== null,
        ]);
    }

    /** A single department — the screens that belong to it, if it is the staff member's to see. */
    public function show(string $slug)
    {
        $user = auth()->user();
        $department = Departments::find($slug);
        abort_if(! $department || ! $department['live'], 404, 'That department is not open yet.');
        abort_unless(Departments::canSee($user, $slug), 403,
            'The '.$department['name'].' screens are outside your department access.');

        return view('admin.departments.show', [
            'department' => $department,
            'tiles' => Departments::tiles($slug),
        ]);
    }
}
