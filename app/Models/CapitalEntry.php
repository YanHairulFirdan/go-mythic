<?php

namespace App\Models;

use Database\Factories\CapitalEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * start_date / end_date are stored as plain Y-m-d strings (UTC). Lexicographic
 * comparison of that format is chronological, so the scopes use plain where().
 * end_date is nullable — NULL means "no fixed end" (open-ended); running totals
 * then treat today as the effective end (see effectiveEndDate()).
 */
#[Fillable(['company_id', 'initial_amount', 'start_date', 'end_date', 'created_by'])]
class CapitalEntry extends Model
{
    /** @use HasFactory<CapitalEntryFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'initial_amount' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function topups(): HasMany
    {
        return $this->hasMany(CapitalTopup::class);
    }

    /**
     * US-MK-01B AC2 "Total Modal Periode Ini": the originally-set amount plus
     * every top-up. Top-ups never touch `initial_amount` (only an explicit edit
     * does); the running total is always derived (reconciles the DBML column
     * name with the PRD prose).
     */
    public function periodTotal(): float
    {
        return (float) $this->initial_amount + (float) $this->topups()->sum('amount');
    }

    /**
     * US-MK-06 / US-MK-01B AC2 "Total Modal Saat Ini": Periode Ini plus every
     * income and minus every expense dated within this entry's period. May be
     * negative — the modal is an indicator, not a hard limit (US-MK-06 AC1).
     * Soft-deleted transactions are excluded (US-TR-03 AC5).
     */
    public function currentTotal(): float
    {
        $inPeriod = Transaction::query()
            ->where('company_id', $this->company_id)
            ->whereBetween('transaction_date', [$this->start_date, $this->effectiveEndDate()]);

        $income = (float) (clone $inPeriod)->where('type', 'income')->sum('amount');
        $expense = (float) (clone $inPeriod)->where('type', 'expense')->sum('amount');

        return $this->periodTotal() + $income - $expense;
    }

    /**
     * The end date to use for running totals: the fixed end_date, or today when
     * the entry is open-ended (C2 of the modal-feedback plan).
     */
    public function effectiveEndDate(): string
    {
        return $this->end_date ?? Carbon::now()->toDateString();
    }

    public function isOpenEnded(): bool
    {
        return $this->end_date === null;
    }

    /**
     * Entries whose [start_date, end_date] range (end inclusive) covers $date.
     * An open-ended entry (end_date NULL) covers every date from start onward.
     */
    public function scopeActiveOn(Builder $query, string $date): Builder
    {
        return $query
            ->where('start_date', '<=', $date)
            ->where(fn (Builder $q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $date));
    }

    /**
     * Entries whose range overlaps [$start, $end] (both inclusive). A null $end
     * means the probe range itself is open-ended; a null end_date means the
     * stored entry is open-ended — either way it extends to infinity.
     */
    public function scopeOverlapping(Builder $query, string $start, ?string $end): Builder
    {
        return $query
            ->when($end !== null, fn (Builder $q) => $q->where('start_date', '<=', $end))
            ->where(fn (Builder $q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $start));
    }
}
