<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Transaction;
use Illuminate\Support\Carbon;

/**
 * US-SUB-01: the Free plan caps transactions at 150 per type (income and expense
 * counted separately) per UTC calendar day, keyed by input time (`created_at`) —
 * PRD "Aturan umum" + modul Subscription. Paid companies are uncapped and do not
 * see the indicator at all.
 *
 * The usage is a live COUNT of non-soft-deleted rows. A Redis counter with a
 * database fallback (US-TR-01B) is a separate task; this COUNT is exactly that
 * documented fallback, so soft-delete restores and income<->expense edits need
 * no separate bookkeeping to stay accurate.
 */
class DailyTransactionQuota
{
    public const LIMIT = 150;

    /** Share of the limit at which the non-blocking soft warning appears (AC2). */
    private const WARNING_RATIO = 0.8;

    private function __construct(
        public readonly bool $applies,
        private readonly int $incomeUsed,
        private readonly int $expenseUsed,
    ) {}

    public static function for(Company $company): self
    {
        if ($company->isPaid()) {
            return new self(applies: false, incomeUsed: 0, expenseUsed: 0);
        }

        $counts = Transaction::query()
            ->where('company_id', $company->id)
            ->whereBetween('created_at', [
                Carbon::now('UTC')->startOfDay(),
                Carbon::now('UTC')->endOfDay(),
            ])
            ->selectRaw('type, COUNT(*) as aggregate')
            ->groupBy('type')
            ->pluck('aggregate', 'type');

        return new self(
            applies: true,
            incomeUsed: (int) ($counts['income'] ?? 0),
            expenseUsed: (int) ($counts['expense'] ?? 0),
        );
    }

    public function used(string $type): int
    {
        return $type === 'income' ? $this->incomeUsed : $this->expenseUsed;
    }

    public function remaining(string $type): int
    {
        return max(0, self::LIMIT - $this->used($type));
    }

    /** AC3: the hard block — this type has hit the daily limit on a Free plan. */
    public function isReached(string $type): bool
    {
        return $this->applies && $this->used($type) >= self::LIMIT;
    }

    /** AC2: the non-blocking soft warning — 80%+ of the limit, not yet blocked. */
    public function isNearLimit(string $type): bool
    {
        return $this->applies
            && ! $this->isReached($type)
            && $this->used($type) >= (int) ceil(self::LIMIT * self::WARNING_RATIO);
    }

    /**
     * AC1: the per-type dashboard indicator payload. Null for Paid companies,
     * which do not render the indicator.
     *
     * @return array{limit: int, income: array{used: int, remaining: int, near_limit: bool, reached: bool}, expense: array{used: int, remaining: int, near_limit: bool, reached: bool}}|null
     */
    public function widget(): ?array
    {
        if (! $this->applies) {
            return null;
        }

        return [
            'limit' => self::LIMIT,
            'income' => $this->typeWidget('income'),
            'expense' => $this->typeWidget('expense'),
        ];
    }

    /**
     * @return array{used: int, remaining: int, near_limit: bool, reached: bool}
     */
    private function typeWidget(string $type): array
    {
        return [
            'used' => $this->used($type),
            'remaining' => $this->remaining($type),
            'near_limit' => $this->isNearLimit($type),
            'reached' => $this->isReached($type),
        ];
    }
}
