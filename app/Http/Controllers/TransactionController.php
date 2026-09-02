<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\UpdateTransactionRequest;
use App\Models\CapitalEntry;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionController extends Controller
{
    private const ATTACHMENT_DIR = 'transaction-attachments';

    private const PER_PAGE = 20;

    /**
     * US-TR-04: Owner melihat seluruh transaksi toko; Employee hanya miliknya
     * (AC1). Filter jenis / kategori / rentang tanggal (AC2), paginated (AC4).
     */
    public function index(Request $request): Response
    {
        $companyId = $request->user()->company_id;

        $validated = $request->validate([
            'type' => ['nullable', Rule::in(['income', 'expense'])],
            'category_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $type = $validated['type'] ?? null;
        $categoryId = isset($validated['category_id']) ? (int) $validated['category_id'] : null;
        $dateFrom = $validated['date_from'] ?? null;
        $dateTo = $validated['date_to'] ?? null;

        $transactions = Transaction::query()
            ->where('company_id', $companyId)
            ->when(
                $request->user()->role === 'employee',
                fn (Builder $query) => $query->where('created_by', $request->user()->id),
            )
            ->when($type, fn (Builder $query, string $value) => $query->where('type', $value))
            ->when($categoryId, fn (Builder $query, int $id) => $query->where('category_id', $id))
            ->when($dateFrom, fn (Builder $query, string $date) => $query->where('transaction_date', '>=', $date))
            ->when($dateTo, fn (Builder $query, string $date) => $query->where('transaction_date', '<=', $date))
            ->with('category:id,name')
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Transaction $transaction): array => [
                'id' => $transaction->id,
                'type' => $transaction->type,
                'amount' => (float) $transaction->amount,
                'transaction_date' => $transaction->transaction_date,
                'category' => $transaction->category?->name,
                'payment_method' => $transaction->payment_method,
                'notes' => $transaction->notes,
            ]);

        return Inertia::render('Transactions/Index', [
            'transactions' => Inertia::scroll($transactions),
            'categories' => TransactionCategory::query()
                ->where('company_id', $companyId)
                ->orderBy('name')
                ->get(['id', 'name', 'type']),
            'filters' => [
                'type' => $type,
                'category_id' => $categoryId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        // US-INV-03: opened from an invoice detail page with ?invoice_id=… pre-fills
        // the link (and its derived customer). Ignored if it is not a company invoice.
        $prefillInvoiceId = $request->integer('invoice_id') ?: null;
        if ($prefillInvoiceId !== null) {
            $exists = Invoice::query()
                ->where('company_id', $request->user()->company_id)
                ->whereKey($prefillInvoiceId)
                ->exists();
            $prefillInvoiceId = $exists ? $prefillInvoiceId : null;
        }

        return Inertia::render('Transactions/Create', [
            'categories' => $this->companyCategories($request),
            'capitalPeriods' => $this->companyCapitalPeriods($request),
            'invoices' => $this->companyInvoices($request),
            'customers' => $this->companyCustomers($request),
            'employees' => $this->companyEmployees($request),
            'prefill' => ['invoice_id' => $prefillInvoiceId],
        ]);
    }

    public function store(StoreTransactionRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('attachment');
        $invoiceId = $data['invoice_id'] ?? null;

        DB::transaction(function () use ($request, $data, $invoiceId): void {
            // US-INV-02 AC2/AC3/AC5: lock the invoice + re-check the balance.
            $invoiceCustomerId = $this->resolveInvoiceLink(
                $request,
                $invoiceId,
                (float) $data['amount'],
                excludeTransactionId: null,
            );

            // US-CUST-02 AC2: a linked invoice locks customer_id to its own;
            // otherwise the manually-picked customer_id (may be null) stands.
            $data['customer_id'] = $invoiceId !== null
                ? $invoiceCustomerId
                : ($data['customer_id'] ?? null);

            $attachmentPath = $request->hasFile('attachment')
                ? $request->file('attachment')->store(self::ATTACHMENT_DIR, 'local')
                : null;

            Transaction::create([
                ...$data,
                'company_id' => $request->user()->company_id,
                'created_by' => $request->user()->id,
                'attachment_path' => $attachmentPath,
            ]);
        });

        // US-INV-03: a transaction linked to an invoice returns to that invoice
        // (so the updated progress is visible); otherwise to the transaction list.
        return $invoiceId !== null
            ? to_route('invoices.show', $invoiceId)
            : to_route('transactions.index');
    }

    /**
     * US-TR-05: transaction detail. Employees may only open their own rows (AC2).
     */
    public function show(Request $request, Transaction $transaction): Response
    {
        $this->authorizeAccess($request, $transaction);

        $transaction->load(['category:id,name', 'creator:id,name', 'editor:id,name']);

        $wasEdited = $transaction->updated_by !== null;

        return Inertia::render('Transactions/Show', [
            'transaction' => [
                'id' => $transaction->id,
                'type' => $transaction->type,
                'amount' => (float) $transaction->amount,
                'transaction_date' => $transaction->transaction_date,
                'category' => $transaction->category?->name,
                'payment_method' => $transaction->payment_method,
                'notes' => $transaction->notes,
                'recorded_by' => $transaction->creator?->name,
                'created_at' => $transaction->created_at?->toIso8601String(),
                // AC1: "last updated by" only surfaces once the row has been edited.
                'last_updated_by' => $wasEdited ? $transaction->editor?->name : null,
                'last_updated_at' => $wasEdited ? $transaction->updated_at?->toIso8601String() : null,
                'attachment_url' => $transaction->attachment_path !== null
                    ? route('transactions.attachment', $transaction)
                    : null,
                'attachment_download_url' => $transaction->attachment_path !== null
                    ? route('transactions.attachment', ['transaction' => $transaction->id, 'download' => 1])
                    : null,
            ],
        ]);
    }

    /**
     * US-TR-02: mengedit transaksi.
     */
    public function edit(Request $request, Transaction $transaction): Response
    {
        $this->authorizeAccess($request, $transaction);

        return Inertia::render('Transactions/Edit', [
            'transaction' => [
                'id' => $transaction->id,
                'type' => $transaction->type,
                'amount' => (float) $transaction->amount,
                'category_id' => $transaction->category_id,
                'invoice_id' => $transaction->invoice_id,
                'customer_id' => $transaction->customer_id,
                'employee_id' => $transaction->employee_id,
                'transaction_date' => $transaction->transaction_date,
                'payment_method' => $transaction->payment_method,
                'notes' => $transaction->notes,
                'attachment_url' => $transaction->attachment_path !== null
                    ? route('transactions.attachment', $transaction)
                    : null,
            ],
            'categories' => $this->companyCategories($request),
            'capitalPeriods' => $this->companyCapitalPeriods($request),
            'invoices' => $this->companyInvoices($request),
            'customers' => $this->companyCustomers($request),
            'employees' => $this->companyEmployees($request),
        ]);
    }

    /**
     * US-TR-02: AC1 (authorization di UpdateTransactionRequest), AC2/AC3 (spatie
     * mencatat event `updated` dengan old → new). AC4 "transfer quota" saat jenis
     * berubah menunggu infra kuota (US-SUB-01).
     */
    public function update(UpdateTransactionRequest $request, Transaction $transaction): RedirectResponse
    {
        $data = $request->safe()->except('attachment');

        DB::transaction(function () use ($request, $transaction, $data): void {
            $invoiceId = $data['invoice_id'] ?? null;

            // US-INV-02: re-validate the (possibly changed / removed) invoice link.
            $invoiceCustomerId = $this->resolveInvoiceLink(
                $request,
                $invoiceId,
                (float) $data['amount'],
                excludeTransactionId: $transaction->id,
            );

            // US-CUST-02 AC2: linked invoice locks customer_id; otherwise the
            // submitted value (may be null after an unlink) stands.
            $data['customer_id'] = $invoiceId !== null
                ? $invoiceCustomerId
                : ($data['customer_id'] ?? null);

            if ($request->hasFile('attachment')) {
                if ($transaction->attachment_path !== null) {
                    Storage::disk('local')->delete($transaction->attachment_path);
                }
                $data['attachment_path'] = $request->file('attachment')->store(self::ATTACHMENT_DIR, 'local');
            }

            $transaction->update([
                ...$data,
                'updated_by' => $request->user()->id,
            ]);
        });

        return to_route('transactions.show', $transaction);
    }

    /**
     * US-TR-03: soft-delete. spatie mencatat event `deleted` dengan snapshot
     * properties (AC4). "Pemulihan quota" (AC5) menunggu infra kuota (US-SUB-01).
     */
    public function destroy(Request $request, Transaction $transaction): RedirectResponse
    {
        $this->authorizeAccess($request, $transaction);

        $transaction->delete();

        return to_route('transactions.index');
    }

    /**
     * US-TR-01 AC8 / US-TR-05: the attachment follows the transaction's own
     * authorization — same tenant, and Employees only reach their own rows.
     * Served inline (so the detail page can preview the image); `?download=1`
     * forces a save.
     */
    public function attachment(Request $request, Transaction $transaction): StreamedResponse
    {
        $this->authorizeAccess($request, $transaction);
        abort_if($transaction->attachment_path === null, 404);

        $disk = Storage::disk('local');

        return $request->boolean('download')
            ? $disk->download($transaction->attachment_path)
            : $disk->response($transaction->attachment_path);
    }

    /**
     * US-TR-05 AC2: same tenant, and Employees only reach rows they recorded.
     * 404 (not 403) so a foreign row's existence is not disclosed.
     */
    private function authorizeAccess(Request $request, Transaction $transaction): void
    {
        $user = $request->user();

        abort_if($transaction->company_id !== $user->company_id, 404);
        abort_if($user->role === 'employee' && $transaction->created_by !== $user->id, 404);
    }

    private function companyCategories(Request $request): Collection
    {
        return TransactionCategory::query()
            ->where('company_id', $request->user()->company_id)
            ->orderBy('name')
            ->get(['id', 'name', 'type']);
    }

    /** US-CUST-02 AC1: customers to pick from on the income form. */
    private function companyCustomers(Request $request): Collection
    {
        return Customer::query()
            ->where('company_id', $request->user()->company_id)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /** PRD 3.2 / US-CUST-04: the "pelaksana" list (accounted employees + workers). */
    private function companyEmployees(Request $request): Collection
    {
        return Employee::query()
            ->where('company_id', $request->user()->company_id)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * US-INV-02 AC1: invoices to pick from on the income form, with the balance
     * still available to link against.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function companyInvoices(Request $request): Collection
    {
        return Invoice::query()
            ->where('company_id', $request->user()->company_id)
            ->with('customer:id,name')
            ->withSum('items as nominal_total', 'amount')
            ->withSum('transactions as linked_total', 'amount')
            ->latest()
            ->get(['id', 'customer_id', 'created_at'])
            ->map(fn (Invoice $invoice): array => [
                'id' => $invoice->id,
                'customer' => $invoice->customer?->name,
                'nominal_total' => (float) ($invoice->nominal_total ?? 0),
                'remaining' => (float) ($invoice->nominal_total ?? 0) - (float) ($invoice->linked_total ?? 0),
            ]);
    }

    /**
     * US-INV-02 AC2/AC3/AC5: with the invoice row locked, ensure this amount
     * fits the remaining balance and return the customer_id to copy onto the
     * transaction. Returns null when there is no invoice link (unlink clears it).
     */
    private function resolveInvoiceLink(Request $request, ?int $invoiceId, float $amount, ?int $excludeTransactionId): ?int
    {
        if ($invoiceId === null) {
            return null;
        }

        $invoice = Invoice::query()
            ->where('company_id', $request->user()->company_id)
            ->lockForUpdate()
            ->findOrFail($invoiceId);

        $linked = (float) $invoice->transactions()
            ->when($excludeTransactionId, fn (Builder $query, int $id) => $query->where('id', '!=', $id))
            ->sum('amount');

        $remaining = $invoice->nominalTotal() - $linked;

        if ($amount > $remaining) {
            throw ValidationException::withMessages([
                'invoice_id' => sprintf(
                    'Sisa saldo invoice ini Rp%s. Nominal transaksi melebihi sisa saldo.',
                    number_format($remaining, 0, ',', '.'),
                ),
            ]);
        }

        return $invoice->customer_id;
    }

    /**
     * US-MK-04 / US-MK-05: the form disables submit for a date not covered by any
     * capital period.
     */
    private function companyCapitalPeriods(Request $request): Collection
    {
        return CapitalEntry::query()
            ->where('company_id', $request->user()->company_id)
            ->orderBy('start_date')
            ->get(['start_date', 'end_date']);
    }
}
