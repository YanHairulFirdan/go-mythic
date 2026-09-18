<?php

namespace App\Http\Controllers;

use App\Http\Requests\InvoiceRequest;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Transaction;
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
        $statusFilter = (string) $request->string('status', 'all');

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
            ->get(['id', 'customer_id', 'due_date', 'created_at'])
            ->map(fn (Invoice $invoice): array => $this->presentInvoice($invoice))
            ->filter(fn (array $invoice): bool => match ($statusFilter) {
                'jatuh_tempo' => $invoice['is_overdue'],
                'all' => true,
                default => $invoice['status_key'] === $statusFilter,
            })
            ->values();

        return Inertia::render('Invoices/Index', [
            'invoices' => $invoices,
            'filters' => ['search' => $search, 'status' => $statusFilter],
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
                'due_date' => $request->validated('due_date'),
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

        $transactions = Transaction::query()
            ->where('company_id', $invoice->company_id)
            ->where('invoice_id', $invoice->id)
            ->with('category:id,name')
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->get(['id', 'amount', 'transaction_date', 'category_id', 'type'])
            ->map(fn (Transaction $transaction): array => [
                'id' => $transaction->id,
                'amount' => (float) $transaction->amount,
                'transaction_date' => $transaction->transaction_date,
                'category' => $transaction->category?->name,
                'type' => $transaction->type,
            ]);

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
                // US-INV-07: derived payment status + due date, still nothing stored.
                'status_key' => $invoice->paymentStatus(),
                'status' => $invoice->paymentStatusLabel(),
                'due_date' => $invoice->due_date?->toDateString(),
                'is_overdue' => $invoice->isOverdue(),
                'is_frozen' => $invoice->isFrozen(),
                'created_at' => $invoice->created_at?->toDateString(),
            ],
            'transactions' => $transactions,
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
                'due_date' => $invoice->due_date?->toDateString(),
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
                'due_date' => $request->validated('due_date'),
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

    private function presentInvoice(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'customer' => $invoice->customer?->name,
            'nominal_total' => (float) ($invoice->nominal_total ?? $invoice->nominalTotal()),
            'linked_total' => (float) ($invoice->linked_total ?? $invoice->linkedTotal()),
            'remaining' => (float) (($invoice->nominal_total ?? $invoice->nominalTotal()) - ($invoice->linked_total ?? $invoice->linkedTotal())),
            'status_key' => $invoice->paymentStatus(),
            'status' => $invoice->paymentStatusLabel(),
            'due_date' => $invoice->due_date?->toDateString(),
            'is_overdue' => $invoice->isOverdue(),
            'created_at' => $invoice->created_at?->toDateString(),
        ];
    }

    private function authorizeTenant(Request $request, Invoice $invoice): void
    {
        abort_if($invoice->company_id !== $request->user()->company_id, 404);
    }
}
