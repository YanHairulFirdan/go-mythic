<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProfitLossController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user?->role === 'owner', 403);

        $validated = $request->validate([
            'period' => ['sometimes', Rule::in(['today', 'week', 'month', 'custom'])],
            'date_from' => ['nullable', 'date_format:Y-m-d', 'required_if:period,custom'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'required_if:period,custom', 'after_or_equal:date_from'],
        ]);

        $period = $validated['period'] ?? 'month';
        $today = Carbon::now();

        [$dateFrom, $dateTo, $periodLabel] = match ($period) {
            'today' => [$today->toDateString(), $today->toDateString(), 'Hari ini'],
            'week' => [$today->copy()->startOfWeek()->toDateString(), $today->toDateString(), 'Minggu ini'],
            'custom' => [
                $validated['date_from'],
                $validated['date_to'],
                $validated['date_from'].' — '.$validated['date_to'],
            ],
            default => [
                $today->copy()->startOfMonth()->toDateString(),
                $today->toDateString(),
                $today->format('F Y'),
            ],
        };

        $baseQuery = fn (string $type): float => (float) Transaction::query()
            ->where('company_id', $user->company_id)
            ->whereBetween('transaction_date', [$dateFrom, $dateTo])
            ->where('type', $type)
            ->sum('amount');

        $income = $baseQuery('income');
        $expense = $baseQuery('expense');

        return Inertia::render('Reports/ProfitLoss', [
            'report' => [
                'period' => $period,
                'period_label' => $periodLabel,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'income' => $income,
                'expense' => $expense,
                'net' => $income - $expense,
                'incomeBreakdown' => [],
                'expenseBreakdown' => [],
            ],
        ]);
    }
}
