<?php

namespace App\Providers;

use App\Observers\ActivityObserver;
use App\Support\Audit;
use Illuminate\Support\ServiceProvider;

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
        foreach (Audit::WATCHED as $model) {
            $model::observe(ActivityObserver::class);
        }
    }
}
