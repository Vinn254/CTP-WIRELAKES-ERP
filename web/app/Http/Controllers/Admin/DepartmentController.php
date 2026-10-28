<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Trainer;
use App\Support\Departments;
use App\Support\Roles;
use App\Support\Workspace;

class DepartmentController extends Controller
{
    /** Landing page after login. A super admin runs the programme by module; everyone else lands on their own desks. */
    public function index()
    {
        $user = auth()->user();

        if ($user->isSuperAdmin()) {
            return view('admin.departments.index', [
                'departments' => Departments::visibleFor($user),
                'isSuper' => true,
                'teaching' => Trainer::forAccount($user)->first(),
            ]);
        }

        return view('admin.workspace.overview', [
            'groups' => Workspace::groups($user),
            'waiting' => Workspace::waiting($user),
            'roleNames' => Roles::namesFor($user->roleSlugs()),
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
