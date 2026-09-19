<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Tenancy;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantMiddleware
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
            abort(404, 'Workspace not found.');
        }

        $tenant = $this->tenancy->tenant;

        if (! $tenant) {
            abort(404, 'Workspace not found.');
        }

        if (! $tenant->is_active) {
            abort(403, 'This workspace has been deactivated.');
        }

        $user = $request->user();

        if (! $user) {
            return redirect('/login');
        }

        $isMember = \DB::connection('mysql')->table('tenant_users')
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->exists();

        if (! $isMember) {
            abort(403, 'You do not have access to this workspace.');
        }

        return $next($request);
    }
}
