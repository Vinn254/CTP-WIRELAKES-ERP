<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Departments;

class DepartmentController extends Controller
{
    /** Landing page after login — the departments (modules) only. */
    public function index()
    {
        return view('admin.departments.index', [
            'departments' => Departments::all(),
        ]);
    }

    /** A single department — the screens that belong to it. */
    public function show(string $slug)
    {
        $department = Departments::find($slug);
        abort_if(! $department || ! $department['live'], 404, 'That department is not open yet.');

        return view('admin.departments.show', [
            'department' => $department,
            'tiles' => Departments::tiles($slug),
        ]);
    }
}
