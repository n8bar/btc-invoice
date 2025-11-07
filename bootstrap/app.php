<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        AppServiceProvider::class,
        AuthServiceProvider::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $renderForbidden = function (Request $request, ?string $details = null) {
            $fallback = "Sorry, you don't have permission.";

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $details ?: $fallback,
                ], 403);
            }

            return response()->view('errors.403', [
                'details' => $details,
            ], 403);
        };

        $exceptions->render(function (AuthorizationException $exception, Request $request) use ($renderForbidden) {
            return $renderForbidden($request, $exception->getMessage());
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) use ($renderForbidden) {
            if ($exception->getStatusCode() !== 403) {
                return null;
            }

            return $renderForbidden($request, $exception->getMessage());
        });
    })->create();
