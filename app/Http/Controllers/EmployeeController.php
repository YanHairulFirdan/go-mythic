<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureCompanySubscription;
use App\Http\Requests\StoreEmployeeAccountRequest;
use App\Http\Requests\StoreWorkerRequest;
use App\Models\Employee;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'canCreateEmployee' => EnsureCompanySubscription::isPaid(
                $user->company_id,
                $user->company->paid_until,
            ),
            'employees' => Employee::query()
                ->with(['user:id,username'])
                ->where('company_id', $user->company_id)
                ->latest()
                ->get(['id', 'user_id', 'name', 'has_access_to_system', 'status']),
        ]);
    }

    /**
     * US-CUST-04: detail Employee/Worker dengan breakdown transaksi (SUM + COUNT
     * di mana employee_id = orang ini), dihitung on-the-fly (AC1/AC3). Owner-only
     * — "Kelola Karyawan" adalah surface Owner (US-RP-01 AC1).
     */
    public function show(Request $request, Employee $employee): Response
    {
        abort_unless($request->user()?->role === 'owner', 403);
        abort_if($employee->company_id !== $request->user()->company_id, 404);

        $linked = Transaction::query()
            ->where('company_id', $employee->company_id)
            ->where('employee_id', $employee->id);

        return Inertia::render('Employees/Show', [
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'has_access_to_system' => $employee->has_access_to_system,
                'status' => $employee->status,
            ],
            'breakdown' => [
                'total' => (float) (clone $linked)->sum('amount'),
                'count' => (clone $linked)->count(),
            ],
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
        if (! EnsureCompanySubscription::isPaid(
            $owner->company_id,
            $owner->company->paid_until,
        )) {
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
}
