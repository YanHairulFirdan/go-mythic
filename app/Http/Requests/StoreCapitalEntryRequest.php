<?php

namespace App\Http\Requests;

use App\Models\CapitalEntry;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class StoreCapitalEntryRequest extends FormRequest
{
    /** Inclusive day-span for each preset (US-MK-01 AC2/AC3). */
    private const PRESET_SPANS = [
        '1_day' => 1,
        '1_week' => 7,
        '1_month' => 30,
    ];

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
            'duration' => ['required', 'in:1_day,1_week,1_month,custom'],
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
     * Effective [start, end] Y-m-d strings for this request (UTC).
     *
     * @return array{0: string, 1: string}
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
        $span = self::PRESET_SPANS[$this->input('duration')] ?? 1;

        return [$start->toDateString(), $start->copy()->addDays($span - 1)->toDateString()];
    }
}
