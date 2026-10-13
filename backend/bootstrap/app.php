<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use App\Http\Middleware\ResolveOdkWorkspace;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('odk:expire-subscriptions')->dailyAt('00:10');
    })
    ->withMiddleware(function (Middleware $middleware): void {
        // Workspace resolution runs after Sanctum on authenticated route groups.
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API clients must receive JSON instead of a web login redirect. This
        // also prevents an undefined Route [login] error for protected files.
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return null;
        });
    })->create();
