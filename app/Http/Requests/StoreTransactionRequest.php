<?php

namespace App\Http\Requests;

use App\Models\CapitalEntry;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // US-TR-01: both Owner and Employee may record transactions.
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'type' => ['required', Rule::in(['income', 'expense'])],
            'amount' => ['required', 'numeric', 'gt:0'], // AC5
            'category_id' => [
                'required',
                Rule::exists('transaction_categories', 'id')
                    ->where('company_id', $companyId)
                    ->where('type', $this->input('type'))
                    ->whereNull('deleted_at'),
            ],
            // AC2: not after "today" in UTC.
            'transaction_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.Carbon::now('UTC')->toDateString()],
            'payment_method' => ['required', Rule::in(['cash', 'transfer', 'qris', 'other'])],
            'notes' => ['nullable', 'string', 'max:2000'],
            // AC8: one optional image, not GIF, max 1 MB.
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:1024'],
            // US-INV-02: nullable link to a company invoice (income only, AC4).
            // The SUM/balance check + lockForUpdate (AC2/AC5) run in the controller.
            'invoice_id' => [
                'nullable',
                'integer',
                Rule::exists('invoices', 'id')
                    ->where('company_id', $companyId)
                    ->whereNull('deleted_at'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category_id.exists' => 'Kategori tidak valid untuk jenis transaksi ini.',
            'transaction_date.before_or_equal' => 'Tanggal transaksi tidak boleh melebihi hari ini.',
            'attachment.mimes' => 'Lampiran harus berupa gambar JPG, PNG, atau WEBP (bukan GIF).',
            'attachment.max' => 'Ukuran lampiran maksimal 1 MB.',
        ];
    }

    /**
     * AC2 + AC7 (US-MK-04): the transaction date must fall inside an active
     * capital entry — this rejects both "no modal at all" and "date outside the
     * modal period".
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $date = $this->input('transaction_date');

            if (! is_string($date) || $validator->errors()->has('transaction_date')) {
                return;
            }

            $covered = CapitalEntry::query()
                ->where('company_id', $this->user()->company_id)
                ->activeOn($date)
                ->exists();

            if (! $covered) {
                $validator->errors()->add(
                    'transaction_date',
                    'Belum ada modal/kas aktif untuk tanggal tersebut. Set modal dulu sebelum mencatat transaksi.',
                );
            }
        });

        // US-INV-02 AC4: an invoice can only be linked to an income transaction.
        $validator->after(function (Validator $validator): void {
            if ($this->filled('invoice_id') && $this->input('type') !== 'income') {
                $validator->errors()->add('invoice_id', 'Invoice hanya bisa dikaitkan ke transaksi pemasukan.');
            }
        });
    }
}
