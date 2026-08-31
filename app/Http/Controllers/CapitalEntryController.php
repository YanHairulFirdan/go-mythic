<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCapitalEntryRequest;
use App\Http\Requests\TopUpCapitalEntryRequest;
use App\Models\CapitalEntry;
use App\Models\CapitalTopup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CapitalEntryController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeOwner($request);

        $active = CapitalEntry::query()
            ->where('company_id', $request->user()->company_id)
            ->activeOn(Carbon::now()->toDateString())
            ->latest('start_date')
            ->first();

        return Inertia::render('Capital/Index', [
            'activeEntry' => $active ? [
                'id' => $active->id,
                'initial_amount' => (float) $active->initial_amount,
                // US-MK-01B AC2: accumulated (initial + top-ups). "Total Modal
                // Saat Ini" (minus net expense) waits on the transactions table.
                'period_total' => $active->periodTotal(),
                'start_date' => $active->start_date,
                'end_date' => $active->end_date,
            ] : null,
        ]);
    }

    public function store(StoreCapitalEntryRequest $request): RedirectResponse
    {
        $this->authorizeOwner($request);

        [$start, $end] = $request->resolvedRange();

        CapitalEntry::create([
            'company_id' => $request->user()->company_id,
            'created_by' => $request->user()->id,
            'initial_amount' => $request->validated('initial_amount'),
            'start_date' => $start,
            'end_date' => $end,
        ]);

        return to_route('capital.index');
    }

    /**
     * US-MK-03: read-only history of every capital entry (newest first) with its
     * top-ups. Owner-only (AC3).
     */
    public function history(Request $request): Response
    {
        $this->authorizeOwner($request);

        $today = Carbon::now()->toDateString();

        $entries = CapitalEntry::query()
            ->where('company_id', $request->user()->company_id)
            ->with('topups:id,capital_entry_id,amount,changed_at,extended_end_date')
            ->orderByDesc('created_at')
            ->get(['id', 'initial_amount', 'start_date', 'end_date', 'created_at'])
            ->map(fn (CapitalEntry $entry): array => [
                'id' => $entry->id,
                'final_amount' => (float) $entry->initial_amount + (float) $entry->topups->sum('amount'),
                'start_date' => $entry->start_date,
                'end_date' => $entry->end_date,
                'status' => ($entry->start_date <= $today && $entry->end_date >= $today) ? 'Aktif' : 'Kadaluarsa',
                'created_at' => $entry->created_at?->toDateString(),
                'topups' => $entry->topups
                    ->sortBy('changed_at')
                    ->values()
                    ->map(fn (CapitalTopup $topup): array => [
                        'id' => $topup->id,
                        'amount' => (float) $topup->amount,
                        'changed_at' => $topup->changed_at?->toDateString(),
                        'extended_end_date' => $topup->extended_end_date,
                    ]),
            ]);

        return Inertia::render('Capital/History', [
            'entries' => $entries,
        ]);
    }

    /**
     * US-MK-01B: top-up edits the same active entry — the running total is
     * derived, so only a history row is added (and end_date optionally extended).
     */
    public function topUp(TopUpCapitalEntryRequest $request, CapitalEntry $capitalEntry): RedirectResponse
    {
        DB::transaction(function () use ($request, $capitalEntry): void {
            $extendedEndDate = $request->validated('extended_end_date');

            CapitalTopup::create([
                'capital_entry_id' => $capitalEntry->id,
                'amount' => $request->validated('amount'),
                'changed_by' => $request->user()->id,
                'changed_at' => Carbon::now(),
                'extended_end_date' => $extendedEndDate,
            ]);

            if ($extendedEndDate !== null) {
                $capitalEntry->update([
                    'end_date' => Carbon::parse($extendedEndDate)->toDateString(),
                ]);
            }
        });

        return to_route('capital.index');
    }

    /**
     * US-RP-01 / PRD 3.6: the Modal/Kas management screen is Owner-only;
     * Employees get 403 even hitting the endpoint directly.
     */
    private function authorizeOwner(Request $request): void
    {
        abort_unless($request->user()->role === 'owner', 403);
    }
}
