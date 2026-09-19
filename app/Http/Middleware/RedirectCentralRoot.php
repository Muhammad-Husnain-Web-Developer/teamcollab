<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectCentralRoot
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $centralDomains = config('tenancy.central_domains', ['localhost', '127.0.0.1']);

        if ($request->is('/') && in_array($request->getHost(), $centralDomains)) {
            return auth()->check()
                ? redirect()->route('workspaces.index')
                : redirect()->route('login');
        }

        return $next($request);
    }
}
