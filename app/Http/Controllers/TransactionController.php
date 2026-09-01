<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransactionRequest;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'categories' => TransactionCategory::query()
                ->where('company_id', $request->user()->company_id)
                ->orderBy('name')
                ->get(['id', 'name', 'type']),
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
            ],
        ]);
    }

    /**
     * US-TR-01 AC8 / US-TR-05: the attachment follows the transaction's own
     * authorization — same tenant, and Employees only reach their own rows.
     */
    public function attachment(Request $request, Transaction $transaction): StreamedResponse
    {
        $this->authorizeAccess($request, $transaction);
        abort_if($transaction->attachment_path === null, 404);

        return Storage::disk('local')->download($transaction->attachment_path);
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
}
