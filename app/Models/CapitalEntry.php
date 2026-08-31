<?php

namespace App\Models;

use Database\Factories\CapitalEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * start_date / end_date are stored as plain Y-m-d strings (UTC). Lexicographic
 * comparison of that format is chronological, so the scopes use plain where().
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

    /**
     * Entries whose [start_date, end_date] range (end inclusive) covers $date.
     */
    public function scopeActiveOn(Builder $query, string $date): Builder
    {
        return $query->where('start_date', '<=', $date)->where('end_date', '>=', $date);
    }

    /**
     * Entries whose range overlaps [$start, $end] (both inclusive).
     */
    public function scopeOverlapping(Builder $query, string $start, string $end): Builder
    {
        return $query->where('start_date', '<=', $end)->where('end_date', '>=', $start);
    }
}
