<?php

namespace App\Models;

use Database\Factories\TransactionCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['company_id', 'name', 'type', 'is_default'])]
class TransactionCategory extends Model
{
    /** @use HasFactory<TransactionCategoryFactory> */
    use HasFactory, SoftDeletes;

    /**
     * PRD 3.2: preset kategori yang di-seed untuk setiap company baru. Kategori
     * income dan expense terpisah (US-TR-01 AC1).
     *
     * @var array<string, list<string>>
     */
    public const PRESETS = [
        'income' => [
            'Penjualan',
            'Jasa',
            'Pendapatan Lain-lain',
        ],
        'expense' => [
            'Pembelian Stok/Bahan',
            'Gaji & Upah',
            'Sewa Tempat',
            'Listrik & Air',
            'Transportasi',
            'Peralatan & Perlengkapan',
            'Biaya Pemasaran',
            'Pengeluaran Lain-lain',
        ],
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Idempotently create the preset categories for a company.
     */
    public static function seedDefaultsFor(Company $company): void
    {
        foreach (self::PRESETS as $type => $names) {
            foreach ($names as $name) {
                self::query()->firstOrCreate(
                    ['company_id' => $company->id, 'type' => $type, 'name' => $name],
                    ['is_default' => true],
                );
            }
        }
    }
}
