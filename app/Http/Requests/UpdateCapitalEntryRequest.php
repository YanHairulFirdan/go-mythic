<?php

namespace App\Http\Requests;

use App\Models\CapitalEntry;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class UpdateCapitalEntryRequest extends FormRequest
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
        return [
            'initial_amount' => ['required', 'numeric', 'gt:0'],
            'start_date' => ['required', 'date'],
            // Nullable: an open-ended entry stores end_date = NULL.
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var CapitalEntry $entry */
            $entry = $this->route('capitalEntry');
            [$start, $end] = $this->resolvedRange();

            $overlaps = CapitalEntry::query()
                ->where('company_id', $this->user()->company_id)
                ->whereKeyNot($entry->getKey())
                ->overlapping($start, $end)
                ->exists();

            if ($overlaps) {
                // A company still has at most one entry per date range; editing
                // must not collide with a *different* entry (US-MK-01 AC1).
                $validator->errors()->add('start_date', 'Sudah ada modal lain untuk rentang tanggal tersebut.');
            }
        });
    }

    /**
     * Effective [start, end] Y-m-d strings for this request (UTC). `end` is null
     * for an open-ended entry.
     *
     * @return array{0: string, 1: string|null}
     */
    public function resolvedRange(): array
    {
        $end = $this->input('end_date');

        return [
            Carbon::parse($this->input('start_date'))->toDateString(),
            $end !== null && $end !== '' ? Carbon::parse($end)->toDateString() : null,
        ];
    }
}
