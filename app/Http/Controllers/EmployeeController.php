<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkerRequest;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function index(): Response
    {
        $user = request()->user();

        abort_unless($user?->role === 'owner', 403);

        return Inertia::render('Employees/Index', [
            'employees' => Employee::query()
                ->where('company_id', $user->company_id)
                ->latest()
                ->get(['id', 'name', 'has_access_to_system', 'status']),
        ]);
    }

    public function store(StoreWorkerRequest $request): RedirectResponse
    {
        Employee::create([
            'company_id' => $request->user()->company_id,
            'name' => $request->validated('name'),
            'has_access_to_system' => false,
            'user_id' => null,
            'status' => 'active',
        ]);

        return to_route('employees.index');
    }
}
