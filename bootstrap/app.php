<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [\App\Http\Middleware\ForceJsonResponse::class]);
        
        // Register feature restriction middleware
        $middleware->alias([
            'feature.restrict' => \App\Http\Middleware\FeatureRestrictionMiddleware::class,
            'security.headers' => \App\Http\Middleware\SecurityHeadersMiddleware::class,
            'disable.tracking.dev' => \App\Http\Middleware\DisableTrackingInDev::class,
            'license.check' => \App\Http\Middleware\CheckLicenseMiddleware::class,
        ]);
        
        // Apply security headers to all requests
        $middleware->append(\App\Http\Middleware\SecurityHeadersMiddleware::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
