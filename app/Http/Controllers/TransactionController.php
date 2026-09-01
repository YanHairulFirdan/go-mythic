<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\UpdateTransactionRequest;
use App\Models\CapitalEntry;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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
        return Inertia::render('Transactions/Create', [
            'categories' => $this->companyCategories($request),
            'capitalPeriods' => $this->companyCapitalPeriods($request),
        ]);
    }

    public function store(StoreTransactionRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('attachment');

        DB::transaction(function () use ($request, $data): void {
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

        return to_route('transactions.index');
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
                'transaction_date' => $transaction->transaction_date,
                'payment_method' => $transaction->payment_method,
                'notes' => $transaction->notes,
                'attachment_url' => $transaction->attachment_path !== null
                    ? route('transactions.attachment', $transaction)
                    : null,
            ],
            'categories' => $this->companyCategories($request),
            'capitalPeriods' => $this->companyCapitalPeriods($request),
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
