<?php

namespace App\Http\Requests;

use App\Models\CapitalEntry;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class TopUpCapitalEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $entry = $this->route('capitalEntry');

        // Owner-only (PRD 3.6) and tenant-scoped.
        return $this->user()?->role === 'owner'
            && $entry instanceof CapitalEntry
            && $entry->company_id === $this->user()->company_id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $entry = $this->route('capitalEntry');

        return [
            'amount' => ['required', 'numeric', 'gt:0'],
            // US-MK-01B AC4: optional; when given it must genuinely extend.
            'extended_end_date' => ['nullable', 'date', 'after:'.$entry->end_date],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var CapitalEntry $entry */
            $entry = $this->route('capitalEntry');
            $today = Carbon::now()->toDateString();

            // AC1: top-up applies to the currently active entry only.
            if ($entry->start_date > $today || $entry->end_date < $today) {
                $validator->errors()->add('amount', 'Modal ini sudah tidak aktif.');
            }
        });
    }
}
