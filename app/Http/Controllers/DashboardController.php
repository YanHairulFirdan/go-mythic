<?php

namespace App\Http\Controllers;

use App\Models\CapitalEntry;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Support\DailyTransactionQuota;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Dashboard', [
            // Performance card: real income/expense/net, framed around the active
            // capital period for an Owner (laba ÷ modal) or the running month
            // otherwise. The greeting name comes from the shared `auth.user` prop.
            'summary' => $this->summaryWidget($request),
            // Newest transactions the viewer may see (Employee: only their own).
            'recentTransactions' => $this->recentTransactions($request),
            'capitalWidget' => $this->capitalWidget($request),
            // US-SUB-01 AC1/AC2: per-type daily usage indicator for Free
            // companies; null (hidden) once the company is Paid.
            'quotaWidget' => DailyTransactionQuota::for($request->user()->company)->widget(),
            // US-INV-06: quick count of invoices not yet fully covered by their
            // linked transactions; null (card hidden) when nothing is outstanding.
            'invoiceReminderWidget' => $this->invoiceReminderWidget($request),
        ]);
    }

    /**
     * The dashboard performance card. Income, expense and net are always summed
     * from the company's non-soft-deleted transactions (same query shape as
     * US-AN-01 AC1/AC3); `income_ratio_percent` drives the comparison bar width.
     *
     * Two framings, chosen per request:
     *  - `basis: 'capital'` — Owner with an active capital entry (US-MK). The
     *    window is that entry's [start_date, end_date], and `change_percent` is
     *    net profit as a percentage of the capital total (laba ÷ modal), a
     *    return figure that cannot blow up on a tiny denominator (modal is
     *    validated > 0, US-MK-01 AC4). `baseline_amount` is the capital total.
     *  - `basis: 'month'` — everyone else (Employee, or Owner with no active
     *    capital). The window is the running calendar month, and `change_percent`
     *    is the month-over-month change of net profit; null when the previous
     *    full month's net is exactly zero. `baseline_amount` is that prior net.
     *
     * @return array{basis: string, income: float, expense: float, net_profit: float, income_ratio_percent: int, change_percent: float|null, baseline_amount: float|null, period_start: string|null, period_end: string|null}
     */
    private function summaryWidget(Request $request): array
    {
        $user = $request->user();
        $companyId = $user->company_id;
        $now = Carbon::now();

        $sumBetween = fn (string $type, string $from, string $to): float => (float) Transaction::query()
            ->where('company_id', $companyId)
            ->where('type', $type)
            ->whereBetween('transaction_date', [$from, $to])
            ->sum('amount');

        $ratio = fn (float $income, float $expense): int => $income + $expense > 0
            ? (int) round($income / ($income + $expense) * 100)
            : 0;

        if ($user->role === 'owner') {
            $capital = CapitalEntry::query()
                ->where('company_id', $companyId)
                ->activeOn($now->toDateString())
                ->latest('start_date')
                ->first();

            if ($capital !== null) {
                $income = $sumBetween('income', $capital->start_date, $capital->end_date);
                $expense = $sumBetween('expense', $capital->start_date, $capital->end_date);
                $netProfit = $income - $expense;
                $modal = $capital->periodTotal();

                return [
                    'basis' => 'capital',
                    'income' => $income,
                    'expense' => $expense,
                    'net_profit' => $netProfit,
                    'income_ratio_percent' => $ratio($income, $expense),
                    'change_percent' => $modal > 0 ? round($netProfit / $modal * 100, 1) : null,
                    'baseline_amount' => $modal,
                    'period_start' => $capital->start_date,
                    'period_end' => $capital->end_date,
                ];
            }
        }

        $income = $sumBetween('income', $now->copy()->startOfMonth()->toDateString(), $now->toDateString());
        $expense = $sumBetween('expense', $now->copy()->startOfMonth()->toDateString(), $now->toDateString());
        $netProfit = $income - $expense;

        $prevStart = $now->copy()->subMonthNoOverflow()->startOfMonth()->toDateString();
        $prevEnd = $now->copy()->subMonthNoOverflow()->endOfMonth()->toDateString();
        $prevNet = $sumBetween('income', $prevStart, $prevEnd) - $sumBetween('expense', $prevStart, $prevEnd);

        return [
            'basis' => 'month',
            'income' => $income,
            'expense' => $expense,
            'net_profit' => $netProfit,
            'income_ratio_percent' => $ratio($income, $expense),
            'change_percent' => $prevNet != 0.0
                ? round(($netProfit - $prevNet) / abs($prevNet) * 100, 1)
                : null,
            'baseline_amount' => $prevNet != 0.0 ? $prevNet : null,
            'period_start' => null,
            'period_end' => null,
        ];
    }

    /**
     * "Transaksi terbaru" list: the five most recent transactions, newest first,
     * scoped to the company. Employees only see rows they recorded (US-TR-04
     * AC1). Empty array renders the section's empty state.
     *
     * @return list<array{id: int, type: string, amount: float, transaction_date: string, category: string|null}>
     */
    private function recentTransactions(Request $request): array
    {
        $user = $request->user();

        return Transaction::query()
            ->where('company_id', $user->company_id)
            ->when(
                $user->role === 'employee',
                fn (Builder $query) => $query->where('created_by', $user->id),
            )
            ->with('category:id,name')
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn (Transaction $transaction): array => [
                'id' => $transaction->id,
                'type' => $transaction->type,
                'amount' => (float) $transaction->amount,
                'transaction_date' => $transaction->transaction_date,
                'category' => $transaction->category?->name,
            ])
            ->all();
    }

    /**
     * US-INV-06: dashboard reminder for invoices still awaiting transactions.
     * Invoices carry no stored status or due date (US-INV-01 AC3); both counts
     * are derived on-the-fly from SUM(items) vs SUM(non-soft-deleted linked
     * transactions), consistent with US-INV-04 AC1. Visible to Owner & Employee
     * (US-INV-05 AC3), scoped to the current company. Returns null when every
     * invoice is fully covered so the card can be hidden entirely.
     *
     * @return array{outstanding: int, partial: int}|null
     */
    private function invoiceReminderWidget(Request $request): ?array
    {
        $invoices = Invoice::query()
            ->where('company_id', $request->user()->company_id)
            ->withSum('items as nominal_total', 'amount')
            ->withSum('transactions as linked_total', 'amount')
            ->get(['id']);

        $outstanding = 0;
        $partial = 0;

        foreach ($invoices as $invoice) {
            $total = (float) ($invoice->nominal_total ?? 0);
            $linked = (float) ($invoice->linked_total ?? 0);

            if ($total <= 0 || $linked >= $total) {
                continue;
            }

            $outstanding++;

            if ($linked > 0) {
                $partial++;
            }
        }

        return $outstanding === 0 ? null : ['outstanding' => $outstanding, 'partial' => $partial];
    }

    /**
     * US-MK-02: running-capital widget. Owner-only (PRD 3.6 — Modal/Kas is not
     * an Employee surface). "Total Modal Saat Ini" (US-MK-06) is Periode Ini plus
     * income minus expense within the period; it may be negative.
     *
     * @return array{period_total: float, current_total: float, start_date: string, end_date: string}|null
     */
    private function capitalWidget(Request $request): ?array
    {
        if ($request->user()->role !== 'owner') {
            return null;
        }

        $active = CapitalEntry::query()
            ->where('company_id', $request->user()->company_id)
            ->activeOn(Carbon::now()->toDateString())
            ->latest('start_date')
            ->first();

        if ($active === null) {
            return null;
        }

        return [
            'period_total' => $active->periodTotal(),
            'current_total' => $active->currentTotal(),
            'start_date' => $active->start_date,
            'end_date' => $active->end_date,
        ];
    }
}
