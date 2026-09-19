<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Tenancy;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscription
{
    public function __construct(private readonly Tenancy $tenancy)
    {
    }

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->tenancy->initialized) {
            return $next($request);
        }

        $tenant = $this->tenancy->tenant;

        if (! $tenant) {
            return $next($request);
        }

        $subscription = $tenant->subscription;

        if (! $subscription || $subscription->hasExpired()) {
            // Subscription check: redirect to workspaces if no valid plan
            // (billing routes not yet implemented — allow access in dev)
            return $next($request);
        }

        return $next($request);
    }
}
