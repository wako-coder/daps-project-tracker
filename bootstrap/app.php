<?php

use App\Http\Middleware\EnsureOrganizationMember;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias(['member' => EnsureOrganizationMember::class]);

        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/orgs');

        // The app runs behind the host's TLS-terminating proxy; trust its
        // X-Forwarded-* headers so generated URLs and cookies use https.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
