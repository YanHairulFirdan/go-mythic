<?php

namespace App\Http\Requests;

use App\Support\DailyTransactionQuota;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Carbon;

/**
 * US-TR-02: mengedit transaksi. Aturan validasi identik dengan pembuatan
 * (jenis/nominal/kategori/tanggal/metode/catatan/lampiran + rentang modal aktif);
 * yang beda hanya authorization — Employee cuma boleh mengedit transaksinya
 * sendiri (AC1) — dan perlakuan kuota harian (AC4).
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

    /**
     * US-TR-02 AC4: an edit only touches the daily quota when it flips
     * income<->expense. The move itself is automatic — Transaction::booted drops
     * the day's cached counts, so the next read rebalances the two types. This
     * guard only rejects the flip when the target type is already full on a Free
     * plan, leaving the row unchanged. A same-type edit never hits the quota,
     * even when that type sits at the limit.
     */
    protected function afterQuota(Validator $validator): void
    {
        $newType = $this->input('type');
        $transaction = $this->route('transaction');

        if (! in_array($newType, ['income', 'expense'], true) || $newType === $transaction->type) {
            return;
        }

        // Only a row inside today's UTC window contributes to the counter, so
        // only then is there quota to transfer / a limit to bump against.
        $today = Carbon::now('UTC');
        $countsToday = $transaction->created_at?->betweenIncluded(
            $today->copy()->startOfDay(),
            $today->copy()->endOfDay(),
        ) ?? false;

        if (! $countsToday) {
            return;
        }

        if (DailyTransactionQuota::for($this->user()->company)->isReached($newType)) {
            $validator->errors()->add('quota', sprintf(
                'Kuota transaksi %s harian (%d) sudah penuh. Perubahan jenis ditolak; data transaksi tidak diubah.',
                $newType === 'income' ? 'pemasukan' : 'pengeluaran',
                DailyTransactionQuota::LIMIT,
            ));
        }
    }
}
