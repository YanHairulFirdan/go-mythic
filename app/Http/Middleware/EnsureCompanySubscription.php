<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanySubscription
{
    public const EXPIRED_MESSAGE = 'Langganan sudah tidak aktif. Silakan perpanjang untuk memulihkan akses Employee.';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $company = $user->company;

        if (! $company || $company->status === 'closed') {
            return $next($request);
        }

        $paid = self::isPaid($company->id, $company->paid_until);

        $expired = $company->paid_until !== null && ! $paid;

        if ($expired) {
            self::degrade($company->id);

            if ($user->role === 'employee') {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->with(
                    'error',
                    'Akun Employee tidak aktif. Alasan: subscription_expired.',
                );
            }

            if ($company->paid_until !== null && ! $request->routeIs('subscription.index', 'subscription.payment.store')) {
                return to_route('subscription.index')->with('warning', self::EXPIRED_MESSAGE);
            }
        }

        return $next($request);
    }

    public static function isPaid(int $companyId, mixed $paidUntil = null): bool
    {
        $cacheKey = self::cacheKey($companyId);
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            $livePaid = $paidUntil?->greaterThanOrEqualTo(now()) === true;

            if (! $livePaid) {
                Cache::forget($cacheKey);
            }

            return $livePaid;
        }

        $paid = $paidUntil?->greaterThanOrEqualTo(now()) === true;

        if ($paid && $paidUntil !== null) {
            $seconds = max(1, min(300, (int) now()->diffInRealSeconds($paidUntil)));
            Cache::put($cacheKey, true, now()->addSeconds($seconds));
        }

        return $paid;
    }

    public static function invalidate(int $companyId): void
    {
        Cache::forget(self::cacheKey($companyId));
    }

    public static function degrade(int $companyId): void
    {
        User::query()
            ->where('company_id', $companyId)
            ->where('role', 'employee')
            ->where('status', 'active')
            ->update([
                'status' => 'inactive',
                'inactive_reason' => 'subscription_expired',
            ]);
    }

    private static function cacheKey(int $companyId): string
    {
        return 'subscription:company:'.$companyId;
    }
}
