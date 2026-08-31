<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCapitalEntryRequest;
use App\Models\CapitalEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
     * US-RP-01 / PRD 3.6: the Modal/Kas management screen is Owner-only;
     * Employees get 403 even hitting the endpoint directly.
     */
    private function authorizeOwner(Request $request): void
    {
        abort_unless($request->user()->role === 'owner', 403);
    }
}
