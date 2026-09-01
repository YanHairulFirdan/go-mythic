<?php

namespace App\Http\Controllers;

use App\Http\Requests\InvoiceRequest;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    /**
     * US-INV-05: list Invoice — Customer, total, progress (SUM transaksi vs total),
     * tanggal (AC1); filter nama Customer (AC2); terbuka Owner & Employee (AC3).
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));

        $invoices = Invoice::query()
            ->where('company_id', $request->user()->company_id)
            ->when(
                $search !== '',
                fn (Builder $query) => $query->whereHas(
                    'customer',
                    fn (Builder $customer) => $customer->where('name', 'like', '%'.$search.'%'),
                ),
            )
            ->with('customer:id,name')
            ->withSum('items as nominal_total', 'amount')
            ->withSum('transactions as linked_total', 'amount')
            ->latest()
            ->get(['id', 'customer_id', 'created_at'])
            ->map(fn (Invoice $invoice): array => [
                'id' => $invoice->id,
                'customer' => $invoice->customer?->name,
                'nominal_total' => (float) ($invoice->nominal_total ?? 0),
                // US-INV-04 AC1: progress is always on-the-fly, never stored.
                'linked_total' => (float) ($invoice->linked_total ?? 0),
                'created_at' => $invoice->created_at?->toDateString(),
            ]);

        return Inertia::render('Invoices/Index', [
            'invoices' => $invoices,
            'filters' => ['search' => $search],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Invoices/Create', $this->formOptions($request));
    }

    public function store(InvoiceRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $invoice = Invoice::create([
                'company_id' => $request->user()->company_id,
                'customer_id' => $request->validated('customer_id'),
                'employee_id' => $request->validated('employee_id'),
                'created_by' => $request->user()->id,
            ]);

            $invoice->items()->createMany($request->validated('items'));
        });

        return to_route('invoices.index');
    }

    public function show(Request $request, Invoice $invoice): Response
    {
        $this->authorizeTenant($request, $invoice);

        $invoice->load(['customer:id,name', 'employee:id,name', 'items:id,invoice_id,description,amount']);

        return Inertia::render('Invoices/Show', [
            'invoice' => [
                'id' => $invoice->id,
                'customer' => $invoice->customer?->only('id', 'name'),
                'employee' => $invoice->employee?->only('id', 'name'),
                'items' => $invoice->items->map->only('id', 'description', 'amount'),
                'nominal_total' => $invoice->nominalTotal(),
                // US-INV-04: progress computed on-the-fly (AC1), shown on detail (AC2).
                'linked_total' => $invoice->linkedTotal(),
                'remaining' => $invoice->remainingBalance(),
                'is_frozen' => $invoice->isFrozen(),
                'created_at' => $invoice->created_at?->toDateString(),
            ],
        ]);
    }

    public function edit(Request $request, Invoice $invoice): Response
    {
        $this->authorizeTenant($request, $invoice);
        abort_if($invoice->isFrozen(), 403);

        $invoice->load('items:id,invoice_id,description,amount');

        return Inertia::render('Invoices/Edit', [
            ...$this->formOptions($request),
            'invoice' => [
                'id' => $invoice->id,
                'customer_id' => $invoice->customer_id,
                'employee_id' => $invoice->employee_id,
                'items' => $invoice->items->map->only('description', 'amount'),
            ],
        ]);
    }

    public function update(InvoiceRequest $request, Invoice $invoice): RedirectResponse
    {
        $this->authorizeTenant($request, $invoice);
        abort_if($invoice->isFrozen(), 403);

        DB::transaction(function () use ($request, $invoice): void {
            $invoice->update([
                'customer_id' => $request->validated('customer_id'),
                'employee_id' => $request->validated('employee_id'),
            ]);

            $invoice->items()->delete();
            $invoice->items()->createMany($request->validated('items'));
        });

        return to_route('invoices.index');
    }

    public function destroy(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorizeTenant($request, $invoice);
        abort_if($invoice->isFrozen(), 403);

        $invoice->delete();

        return to_route('invoices.index');
    }

    /**
     * @return array{customers: Collection, employees: Collection}
     */
    private function formOptions(Request $request): array
    {
        $companyId = $request->user()->company_id;

        return [
            'customers' => Customer::where('company_id', $companyId)->orderBy('name')->get(['id', 'name']),
            'employees' => Employee::where('company_id', $companyId)->orderBy('name')->get(['id', 'name']),
        ];
    }

    private function authorizeTenant(Request $request, Invoice $invoice): void
    {
        abort_if($invoice->company_id !== $request->user()->company_id, 404);
    }
}
