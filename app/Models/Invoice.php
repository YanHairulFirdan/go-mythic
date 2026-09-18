<?php

namespace App\Models;

use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

#[Fillable(['company_id', 'customer_id', 'employee_id', 'created_by', 'due_date'])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory, SoftDeletes;

    protected $casts = [
        'due_date' => 'date',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * US-INV-01 AC2: total is always SUM(invoice_items.amount), never stored.
     */
    public function nominalTotal(): float
    {
        return (float) $this->items()->sum('amount');
    }

    /**
     * US-INV-02 AC2 / US-INV-04: SUM of non-soft-deleted linked transactions
     * and the balance still available to link against.
     */
    public function linkedTotal(): float
    {
        return (float) $this->transactions()->sum('amount');
    }

    public function remainingBalance(): float
    {
        return $this->nominalTotal() - $this->linkedTotal();
    }

    public function paymentStatus(): string
    {
        $total = $this->nominalTotal();
        $linked = $this->linkedTotal();

        return $linked <= 0 ? 'belum_dibayar' : ($linked < $total ? 'dp' : 'lunas');
    }

    public function paymentStatusLabel(): string
    {
        return match ($this->paymentStatus()) {
            'dp' => 'DP',
            'lunas' => 'LUNAS',
            default => 'BELUM DIBAYAR',
        };
    }

    public function isOverdue(?Carbon $today = null): bool
    {
        return $this->remainingBalance() > 0
            && $this->due_date !== null
            && $this->due_date->toDateString() < ($today ?? Carbon::now('UTC'))->toDateString();
    }

    /**
     * US-INV-01 AC4: an invoice is frozen once it has a non-soft-deleted
     * linked transaction — Customer, Worker/Employee and items become read-only.
     */
    public function isFrozen(): bool
    {
        return $this->transactions()->exists();
    }
}
