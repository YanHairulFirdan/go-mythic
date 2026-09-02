<?php

namespace App\Http\Controllers;

use App\Models\CapitalEntry;
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
        ]);
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
