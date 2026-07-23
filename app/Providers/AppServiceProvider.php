<?php

namespace App\Providers;

use App\Models\Issue;
use App\Observers\IssueObserver;
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
        Issue::observe(IssueObserver::class);
    }
}
