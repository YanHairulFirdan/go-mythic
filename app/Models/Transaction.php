<?php

namespace App\Models;

use App\Support\DailyTransactionQuota;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * transaction_date is stored as a plain Y-m-d string (UTC) — same rationale as
 * CapitalEntry: lexicographic comparison of that format is chronological, so the
 * modal-range check (US-TR-01 AC2) needs no date casting.
 */
#[Fillable([
    'company_id',
    'category_id',
    'customer_id',
    'invoice_id',
    'employee_id',
    'created_by',
    'updated_by',
    'type',
    'amount',
    'transaction_date',
    'payment_method',
    'notes',
    'attachment_path',
])]
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    /**
     * US-TR-01B: keep the Redis daily-quota counters coherent. Any create,
     * update (incl. an income<->expense switch), soft-delete or restore drops
     * the day's cached per-type counts so the next read recomputes from the DB.
     */
    protected static function booted(): void
    {
        $flush = static fn (self $transaction) => DailyTransactionQuota::forget($transaction->company_id);

        static::saved($flush);
        static::deleted($flush);
        static::restored($flush);
    }

    /**
     * US-TR-01 AC4: log the `created` event (and later updated/deleted) with the
     * acting user as causer and the meaningful fields as properties.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('transaction')
            ->logOnly([
                'type',
                'amount',
                'category_id',
                'transaction_date',
                'payment_method',
                'notes',
                'customer_id',
                'invoice_id',
                'employee_id',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TransactionCategory::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
