<?php

namespace App\Providers;

use App\Services\DashboardService;
use App\Services\CoverageReplacementAlertService;
use App\Services\SellingInQueryService;
use App\Services\SelloutQueryService;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(SelloutQueryService::class);
        $this->app->bind(SellingInQueryService::class);
        $this->app->singleton(DashboardService::class);
        $this->app->singleton(CoverageReplacementAlertService::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if (config('app.env') === 'local') {
            URL::forceScheme('https');
        }
    }
}
