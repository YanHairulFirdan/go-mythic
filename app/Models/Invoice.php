<?php

namespace App\Models;

use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['company_id', 'customer_id', 'employee_id', 'created_by'])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory, SoftDeletes;

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

    /**
     * US-INV-01 AC4: an invoice is frozen once it has a non-soft-deleted
     * linked transaction — Customer, Worker/Employee and items become read-only.
     */
    public function isFrozen(): bool
    {
        return $this->transactions()->exists();
    }
}
