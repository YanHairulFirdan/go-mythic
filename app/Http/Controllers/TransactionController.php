<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransactionRequest;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionController extends Controller
{
    private const ATTACHMENT_DIR = 'transaction-attachments';

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
     * US-TR-01 AC8 / US-TR-05: the attachment follows the transaction's own
     * authorization — same tenant, and Employees only reach their own rows.
     */
    public function attachment(Request $request, Transaction $transaction): StreamedResponse
    {
        $user = $request->user();

        abort_if($transaction->company_id !== $user->company_id, 404);
        abort_if($user->role === 'employee' && $transaction->created_by !== $user->id, 404);
        abort_if($transaction->attachment_path === null, 404);

        return Storage::disk('local')->download($transaction->attachment_path);
    }
}
