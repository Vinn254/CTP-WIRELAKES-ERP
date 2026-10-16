<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Departments;

class DepartmentController extends Controller
{
    /** Landing page after login — only the departments this staff account is allowed to open. */
    public function index()
    {
        return view('admin.departments.index', [
            'departments' => Departments::visibleFor(auth()->user()),
            'isSuper' => auth()->user()?->isSuperAdmin(),
        ]);
    }

    /** A single department — the screens that belong to it, if it is the staff member's to see. */
    public function show(string $slug)
    {
        $department = Departments::find($slug);
        abort_if(! $department || ! $department['live'], 404, 'That department is not open yet.');
        abort_unless(Departments::canSee(auth()->user(), $slug), 403,
            'The '.$department['name'].' screens are outside your department access.');

        return view('admin.departments.show', [
            'department' => $department,
            'tiles' => Departments::tiles($slug),
        ]);
    }
}
