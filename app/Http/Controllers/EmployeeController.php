<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeeAccountRequest;
use App\Http\Requests\StoreWorkerRequest;
use App\Http\Requests\UpdateEmployeeStatusRequest;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function index(): Response
    {
        $user = request()->user();

        abort_unless($user?->role === 'owner', 403);

        return Inertia::render('Employees/Index', [
            'canCreateEmployee' => $user->company->paid_until?->isFuture() === true,
            'employees' => Employee::query()
                ->with(['user:id,username'])
                ->where('company_id', $user->company_id)
                ->latest()
                ->get(['id', 'user_id', 'name', 'has_access_to_system', 'status']),
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

    public function storeAccount(StoreEmployeeAccountRequest $request): RedirectResponse
    {
        $owner = $request->user();

        // AC2: Free (termasuk pending payment) diarahkan ke halaman pembayaran, bukan error.
        if ($owner->company->paid_until?->isFuture() !== true) {
            return to_route('subscription.index');
        }

        DB::transaction(function () use ($request, $owner): void {
            $employee = User::create([
                'company_id' => $owner->company_id,
                'name' => $request->validated('name'),
                'username' => $request->validated('username'),
                'email' => null,
                'password' => $request->validated('password'),
                'role' => 'employee',
                'status' => 'active',
            ]);

            Employee::create([
                'company_id' => $owner->company_id,
                'user_id' => $employee->id,
                'name' => $employee->name,
                'has_access_to_system' => true,
                'status' => 'active',
            ]);
        });

        return to_route('employees.index');
    }

    public function updateStatus(UpdateEmployeeStatusRequest $request, Employee $employee): RedirectResponse
    {
        abort_unless($employee->company_id === $request->user()->company_id, 404);

        $status = $request->validated('status');

        DB::transaction(function () use ($employee, $status): void {
            $employee->update(['status' => $status]);
            $employee->user?->update([
                'status' => $status,
                'inactive_reason' => $status === 'inactive' ? 'manual' : null,
            ]);
        });

        return back()->with('status', sprintf('Status %s diperbarui menjadi %s.', $employee->name, $status));
    }
}
