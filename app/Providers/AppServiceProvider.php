<?php

namespace App\Providers;

use App\Support\CompanyBranding;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Inject the company's primary-colour CSS variables into the page <head>
        // so a customised theme is correct on first paint (no flash of default).
        view()->composer('app', function (View $view): void {
            $view->with('brandingCss', CompanyBranding::css(request()->user()?->company));
        });
    }
}
