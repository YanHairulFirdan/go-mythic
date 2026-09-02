<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * US-SUB-01 / US-TR-01B: the Free plan caps transactions at 150 per type (income
 * and expense counted separately) per UTC calendar day, keyed by input time
 * (`created_at`) — PRD "Aturan umum" + modul Subscription. Paid companies are
 * uncapped and see no indicator.
 *
 * US-TR-01B: the per-type usage is read through the cache store (Redis in
 * production) under the PRD key `company:{id}:txn_count:{type}:{Ymd}`, which
 * auto-expires at the end of the UTC day. Any write to a company's transactions
 * drops those keys (see Transaction::booted), so the next read recomputes from
 * the database — that makes soft-delete restores and income<->expense edits
 * correct with no counter bookkeeping. If the cache store is unreachable the
 * lookup falls back to a raw database COUNT with identical semantics.
 */
class DailyTransactionQuota
{
    public const LIMIT = 150;

    /** Share of the limit at which the non-blocking soft warning appears. */
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

        try {
            return new self(
                applies: true,
                incomeUsed: self::cachedCount($company->id, 'income'),
                expenseUsed: self::cachedCount($company->id, 'expense'),
            );
        } catch (\Throwable $e) {
            // PRD 3.5: cache store down -> fall back to a live database COUNT.
            report($e);

            return new self(
                applies: true,
                incomeUsed: self::databaseCount($company->id, 'income'),
                expenseUsed: self::databaseCount($company->id, 'expense'),
            );
        }
    }

    /** Drop both cached per-type counters for a company (called after any write). */
    public static function forget(int $companyId): void
    {
        try {
            foreach (['income', 'expense'] as $type) {
                Cache::forget(self::cacheKey($companyId, $type));
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function used(string $type): int
    {
        return $type === 'income' ? $this->incomeUsed : $this->expenseUsed;
    }

    public function remaining(string $type): int
    {
        return max(0, self::LIMIT - $this->used($type));
    }

    /** The hard block — this type has hit the daily limit on a Free plan. */
    public function isReached(string $type): bool
    {
        return $this->applies && $this->used($type) >= self::LIMIT;
    }

    /** The non-blocking soft warning — 80%+ of the limit, not yet blocked. */
    public function isNearLimit(string $type): bool
    {
        return $this->state($type) === 'warning';
    }

    /**
     * US-TR-01B AC3: ring colour band — `normal` (< 80%), `warning` (>= 80% and
     * < 100%), `full` (>= 100%). Reconciles US-SUB-01 AC2 ("mencapai 80%") with
     * US-TR-01B AC3: 80% exactly is already a warning.
     */
    public function state(string $type): string
    {
        if (! $this->applies) {
            return 'normal';
        }

        $used = $this->used($type);

        return match (true) {
            $used >= self::LIMIT => 'full',
            $used >= (int) ceil(self::LIMIT * self::WARNING_RATIO) => 'warning',
            default => 'normal',
        };
    }

    /**
     * US-SUB-01 AC1 / US-TR-01B AC1-AC5: per-type indicator payload. Null for
     * Paid companies, which do not render the indicator at all.
     *
     * @return array{limit: int, income: array<string, mixed>, expense: array<string, mixed>}|null
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
     * @return array{used: int, remaining: int, state: string, near_limit: bool, reached: bool}
     */
    private function typeWidget(string $type): array
    {
        return [
            'used' => $this->used($type),
            'remaining' => $this->remaining($type),
            'state' => $this->state($type),
            'near_limit' => $this->isNearLimit($type),
            'reached' => $this->isReached($type),
        ];
    }

    private static function cachedCount(int $companyId, string $type): int
    {
        return (int) Cache::remember(
            self::cacheKey($companyId, $type),
            self::secondsUntilUtcMidnight(),
            fn (): int => self::databaseCount($companyId, $type),
        );
    }

    private static function databaseCount(int $companyId, string $type): int
    {
        return Transaction::query()
            ->where('company_id', $companyId)
            ->where('type', $type)
            ->whereBetween('created_at', [
                Carbon::now('UTC')->startOfDay(),
                Carbon::now('UTC')->endOfDay(),
            ])
            ->count();
    }

    private static function cacheKey(int $companyId, string $type): string
    {
        return sprintf(
            'company:%d:txn_count:%s:%s',
            $companyId,
            $type,
            Carbon::now('UTC')->format('Ymd'),
        );
    }

    private static function secondsUntilUtcMidnight(): int
    {
        return max(1, (int) Carbon::now('UTC')->diffInSeconds(
            Carbon::now('UTC')->addDay()->startOfDay(),
        ));
    }
}
