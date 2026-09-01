<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransactionCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'owner';
    }

    /**
     * PRD 3.2 / US-TR-01 AC1: kategori income & expense terpisah; nama unik
     * per (company, type) — sesuai unique index tabel.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('transaction_categories', 'name')
                    ->where('company_id', $this->user()->company_id)
                    ->where('type', $this->input('type'))
                    ->ignore($this->route('transaction_category')),
            ],
            'type' => ['required', Rule::in(['income', 'expense'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'Kategori dengan nama ini sudah ada untuk jenis tersebut.',
        ];
    }
}
