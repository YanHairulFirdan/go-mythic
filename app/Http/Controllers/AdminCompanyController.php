<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminCompanyController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:free,paid'],
        ]);

        $companies = Company::query()
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->when($filters['status'] ?? null, function ($query, string $status): void {
                $now = now();

                $status === 'paid'
                    ? $query->where('paid_until', '>', $now)
                    : $query->where(function ($query) use ($now): void {
                        $query->whereNull('paid_until')->orWhere('paid_until', '<=', $now);
                    });
            })
            ->orderBy('name')
            ->get(['id', 'name', 'owner_name', 'email', 'paid_until'])
            ->map(fn (Company $company): array => [
                'id' => $company->id,
                'name' => $company->name,
                'owner_name' => $company->owner_name,
                'email' => $company->email,
                'paid_until' => $company->paid_until?->toDateString(),
                'subscription_status' => $company->isPaid() ? 'Paid' : 'Free',
            ]);

        return Inertia::render('Admin/Companies/Index', [
            'companies' => $companies,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'status' => $filters['status'] ?? '',
            ],
        ]);
    }
}
