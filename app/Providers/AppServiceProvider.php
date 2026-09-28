<?php

namespace App\Providers;

use App\Models\AdvancedItinerary;
use App\Models\WorkTask;
use App\Policies\AdvancedItineraryPolicy;
use App\Policies\WorkTaskPolicy;
use Illuminate\Support\Facades\Gate;
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
        Gate::policy(AdvancedItinerary::class, AdvancedItineraryPolicy::class);
        Gate::policy(WorkTask::class, WorkTaskPolicy::class);
    }
}
