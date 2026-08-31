<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Payment;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function __invoke(): Response
    {
        $now = now();

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'companies' => Company::count(),
                'paid' => Company::where('paid_until', '>', $now)->count(),
                'free' => Company::where(function ($query) use ($now): void {
                    $query->whereNull('paid_until')->orWhere('paid_until', '<=', $now);
                })->count(),
                'pendingPayments' => Payment::where('status', 'pending')->count(),
            ],
        ]);
    }
}
