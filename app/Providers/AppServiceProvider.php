<?php

namespace App\Providers;

use App\Models\Order;
use App\Models\Review;
use App\Observers\ReviewObserver;
use App\Policies\OrderPolicy;
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
        Review::observe(ReviewObserver::class);
        Gate::policy(Order::class, OrderPolicy::class);
    }
}
