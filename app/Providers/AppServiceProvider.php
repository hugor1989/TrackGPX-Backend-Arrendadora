<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use App\Models\Company;
use App\Observers\CompanyObserver;
use App\Models\Plan;
use App\Observers\PlanObserver;
use App\Services\FolioGenerator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
         // Registrar como singleton (una sola instancia)
        $this->app->singleton(FolioGenerator::class, function ($app) {
            return new FolioGenerator();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function ($request) {
            return Limit::perMinute(60)->by(
                $request->user()?->id ?: $request->ip()
            );
        });

        Company::observe(CompanyObserver::class);
        Plan::observe(PlanObserver::class);

    }
}
