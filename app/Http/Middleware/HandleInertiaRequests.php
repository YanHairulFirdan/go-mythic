<?php

namespace App\Http\Middleware;

use App\Models\CapitalEntry;
use App\Support\CompanyBranding;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            // US-MK-05: drives the global non-removable "belum ada modal aktif"
            // banner (rendered by every PrototypeLayout page). False also when the
            // only capital entry has expired (AC4).
            'capitalActive' => $this->hasActiveCapital($request),
            // Owner-configurable primary colour + company logo for the app shell.
            'branding' => CompanyBranding::payload($request->user()?->company),
        ];
    }

    /**
     * Whether the authenticated user's company has a capital entry covering today.
     */
    private function hasActiveCapital(Request $request): bool
    {
        $user = $request->user();

        if ($user === null) {
            return false;
        }

        return CapitalEntry::query()
            ->where('company_id', $user->company_id)
            ->activeOn(Carbon::now()->toDateString())
            ->exists();
    }
}
