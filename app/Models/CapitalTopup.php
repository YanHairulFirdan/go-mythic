<?php

namespace App\Models;

use Database\Factories\CapitalTopupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['capital_entry_id', 'amount', 'changed_by', 'changed_at', 'extended_end_date'])]
class CapitalTopup extends Model
{
    /** @use HasFactory<CapitalTopupFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'changed_at' => 'datetime',
        ];
    }

    public function capitalEntry(): BelongsTo
    {
        return $this->belongsTo(CapitalEntry::class);
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
