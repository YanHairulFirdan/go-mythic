<?php

namespace App\Http\Controllers;

use App\Models\CapitalEntry;
use App\Models\Invoice;
use App\Support\DailyTransactionQuota;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Dashboard', [
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
