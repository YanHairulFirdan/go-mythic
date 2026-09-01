<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * US-INV-01 AC1: customer required, employee optional, at least one item
     * with a description and a positive amount. Total is never client-supplied
     * (AC2) — it is derived from the items.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'customer_id' => [
                'required',
                Rule::exists('customers', 'id')->where('company_id', $companyId)->whereNull('deleted_at'),
            ],
            'employee_id' => [
                'nullable',
                Rule::exists('employees', 'id')->where('company_id', $companyId)->whereNull('deleted_at'),
            ],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.amount' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
