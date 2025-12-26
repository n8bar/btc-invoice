<?php

use App\Console\Commands\AssignInvoiceAddresses;
use App\Console\Commands\BackfillInvoicePayments;
use App\Console\Commands\ReassignInvoiceAddresses;
use App\Console\Commands\SendPastDueInvoiceAlerts;
use App\Console\Commands\WatchInvoicePayments;
use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\EventServiceProvider;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        AppServiceProvider::class,
        AuthServiceProvider::class,
        EventServiceProvider::class,
    ])
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('wallet:watch-payments')
            ->everyMinute()
            ->withoutOverlapping()
            ->runInBackground();
        $schedule->command('invoices:send-past-due-alerts')->dailyAt('02:00');
    })
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        AssignInvoiceAddresses::class,
        BackfillInvoicePayments::class,
        SendPastDueInvoiceAlerts::class,
        WatchInvoicePayments::class,
        ReassignInvoiceAddresses::class,
    ])
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

        $exceptions->render(function (TokenMismatchException $exception, Request $request) {
            if (! $request->routeIs('logout') && ! $request->is('logout')) {
                return null;
            }

            Auth::guard('web')->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            if ($request->expectsJson()) {
                return response()->noContent();
            }

            return redirect('/');
        });
    })->create();
