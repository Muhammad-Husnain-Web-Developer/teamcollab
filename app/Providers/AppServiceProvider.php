<?php

namespace App\Providers;

use App\Models\Tenant;
use App\Policies\WorkspacePolicy;
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
        // Laravel's policy auto-discovery guesses "TenantPolicy" from the
        // Tenant model's class name, but the app's naming convention calls
        // this concept a "workspace" everywhere else — register the mapping
        // explicitly so authorize('view'/'update'/'delete', $tenant) resolves.
        Gate::policy(Tenant::class, WorkspacePolicy::class);
    }
}
