<?php

namespace App\Http\Controllers;

use App\Models\CapitalTopup;
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
            'period' => ['sometimes', Rule::in(['today', 'week', 'month', '2months', 'custom'])],
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
            '2months' => [
                $today->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(),
                $today->toDateString(),
                '2 Bulan Terakhir',
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
        $capitalTopup = (float) CapitalTopup::query()
            ->whereHas('capitalEntry', fn ($query) => $query->where('company_id', $user->company_id))
            ->whereBetween('changed_at', [$dateFrom.' 00:00:00', $dateTo.' 23:59:59'])
            ->sum('amount');

        $breakdown = function (string $type) use ($user, $dateFrom, $dateTo, $income, $expense): array {
            $total = $type === 'income' ? $income : $expense;

            return Transaction::query()
                ->where('company_id', $user->company_id)
                ->whereBetween('transaction_date', [$dateFrom, $dateTo])
                ->where('type', $type)
                ->selectRaw('category_id, SUM(amount) as total')
                ->groupBy('category_id')
                ->orderByDesc('total')
                ->with('category:id,name')
                ->get()
                ->map(fn ($row) => [
                    'label' => $row->category->name,
                    'amount' => (float) $row->total,
                    'percent' => $total > 0 ? round((float) $row->total / $total * 100, 1) : 0,
                ])
                ->values()
                ->all();
        };

        return Inertia::render('Reports/ProfitLoss', [
            'report' => [
                'period' => $period,
                'period_label' => $periodLabel,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'income' => $income,
                'expense' => $expense,
                'net' => $income - $expense,
                'capital_topup' => $capitalTopup,
                'incomeBreakdown' => $breakdown('income'),
                'expenseBreakdown' => $breakdown('expense'),
            ],
        ]);
    }
}
