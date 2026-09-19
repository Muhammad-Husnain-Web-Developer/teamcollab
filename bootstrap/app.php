<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\EnsureTenantMiddleware;
use App\Http\Middleware\CheckSubscription;
use App\Http\Middleware\RedirectCentralRoot;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(RedirectCentralRoot::class);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // On tenant domains redirect unauthenticated users to /login (relative = stays on tenant domain)
        $middleware->redirectGuestsTo(function ($request) {
            if (app(\Stancl\Tenancy\Tenancy::class)->initialized) {
                return '/login';
            }
            return route('login');
        });

        $middleware->alias([
            'tenant'             => EnsureTenantMiddleware::class,
            'check.subscription' => CheckSubscription::class,
            'role'               => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'         => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withProviders([
        \App\Providers\TenancyServiceProvider::class,
        \App\Providers\EventServiceProvider::class,
    ])
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
