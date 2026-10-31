<?php

namespace App\Providers;

use App\Models\CtpRegistration;
use App\Models\Community;
use App\Models\FundiRegistration;
use App\Models\HirerRegistration;
use App\Models\JobApplication;
use App\Models\Trainer;
use App\Observers\ActivityObserver;
use App\Observers\NewMemberObserver;
use App\Observers\NewRegistrationObserver;
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

        CtpRegistration::observe(NewMemberObserver::class);
        FundiRegistration::observe(NewMemberObserver::class);

        // Every other record that lands on a register is announced the same way, so no kind of joining goes
        // unnoticed while the office waits on a login that is never mailed.
        foreach ([Trainer::class, HirerRegistration::class, Community::class, JobApplication::class] as $register) {
            $register::observe(NewRegistrationObserver::class);
        }
    }
}
