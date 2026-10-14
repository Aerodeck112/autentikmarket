<?php

use App\Exceptions\BusinessRuleException;
use App\Http\Middleware\ApplyUserLocale;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureProducer;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetRouteLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Env;

// Fără putenv(): procesele PHP-FPM / mod_php sunt refolosite între request-uri, iar valorile puse cu putenv()
// ar rămâne în mediul procesului și ar masca modificările ulterioare din .env (vezi CRM → Sistem →
// „Reîncarcă configurația” și pasul de cache al Update Manager-ului).
Env::disablePutenv();

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            ApplyUserLocale::class,
            EnsureActiveUser::class,
            SecurityHeaders::class,
        ]);
        $middleware->alias([
            'locale' => SetRouteLocale::class,
            'producer' => EnsureProducer::class,
        ]);
        $middleware->encryptCookies(except: ['locale']);
        $middleware->redirectGuestsTo(fn (Request $request) => lroute('login'));
        $middleware->redirectUsersTo(fn (Request $request) => route('dashboard.redirect'));
        // Pașii Update Manager-ului rulează și în timpul maintenance mode (sunt protejați de auth + system.update).
        $middleware->preventRequestsDuringMaintenance(except: ['crm/system/updates/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->render(function (BusinessRuleException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage(), 'reason' => $e->reason], 422);
            }

            return back()->withInput()->with('error', $e->getMessage());
        });
    })->create();
