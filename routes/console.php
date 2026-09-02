<?php

use App\Models\Company;
use App\Notifications\SubscriptionExpiring;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// US-SUB-04 AC1/AC2: best-effort email reminder 3 days before paid_until.
// Delivery failures are caught per company so they never affect access.
Artisan::command('subscription:remind', function () {
    $targetDate = now()->addDays(3)->toDateString();

    Company::query()
        ->whereNotNull('paid_until')
        ->whereDate('paid_until', $targetDate)
        ->where('status', 'active')
        ->with(['users' => fn ($query) => $query->where('role', 'owner')->where('status', 'active')])
        ->get()
        ->each(function (Company $company): void {
            $owner = $company->users->first();

            if (! $owner) {
                return;
            }

            try {
                $owner->notify(new SubscriptionExpiring($company));
            } catch (Throwable $e) {
                report($e);
            }
        });
})->purpose('Send best-effort subscription expiry reminders (US-SUB-04)');

Schedule::command('subscription:remind')->daily();
