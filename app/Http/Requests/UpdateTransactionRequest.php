<?php

namespace App\Http\Requests;

/**
 * US-TR-02: mengedit transaksi. Aturan validasi identik dengan pembuatan
 * (jenis/nominal/kategori/tanggal/metode/catatan/lampiran + rentang modal aktif);
 * yang beda hanya authorization — Employee cuma boleh mengedit transaksinya
 * sendiri (AC1).
 */
class UpdateTransactionRequest extends StoreTransactionRequest
{
    public function authorize(): bool
    {
        $transaction = $this->route('transaction');
        $user = $this->user();

        abort_if($user === null, 403);
        // 404 (not 403) so a foreign row's existence is not disclosed — matches
        // TransactionController::authorizeAccess().
        abort_if($transaction->company_id !== $user->company_id, 404);
        abort_if($user->role === 'employee' && $transaction->created_by !== $user->id, 404);

        return true;
    }
}
