<?php

namespace App\Http\Requests;

use App\Models\CapitalEntry;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class StoreCapitalEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // "1_year" = one year from today; "no_end" = open-ended (end_date NULL);
            // "custom" = an explicit range.
            'duration' => ['required', 'in:1_year,no_end,custom'],
            'initial_amount' => ['required', 'numeric', 'gt:0'],
            'start_date' => ['nullable', 'required_if:duration,custom', 'date'],
            'end_date' => ['nullable', 'required_if:duration,custom', 'date', 'after_or_equal:start_date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            [$start, $end] = $this->resolvedRange();

            $overlaps = CapitalEntry::query()
                ->where('company_id', $this->user()->company_id)
                ->overlapping($start, $end)
                ->exists();

            if ($overlaps) {
                // US-MK-01 AC1: form is only for when there is no active/overlapping entry.
                $field = $this->input('duration') === 'custom' ? 'start_date' : 'duration';
                $validator->errors()->add($field, 'Sudah ada modal aktif untuk rentang tanggal tersebut.');
            }
        });
    }

    /**
     * Effective [start, end] for this request (UTC Y-m-d). `end` is null for an
     * open-ended entry.
     *
     * @return array{0: string, 1: string|null}
     */
    public function resolvedRange(): array
    {
        if ($this->input('duration') === 'custom') {
            return [
                Carbon::parse($this->input('start_date'))->toDateString(),
                Carbon::parse($this->input('end_date'))->toDateString(),
            ];
        }

        $start = Carbon::now()->startOfDay();

        if ($this->input('duration') === 'no_end') {
            return [$start->toDateString(), null];
        }

        // 1_year: inclusive one-year span ending the day before the anniversary.
        return [$start->toDateString(), $start->copy()->addYear()->subDay()->toDateString()];
    }
}
