<?php

use App\Http\Controllers\AuctionWebhookController;
use App\Http\Middleware\EnsurePhoneIsVerified;
use App\Http\Middleware\EnsureUserIsNotBlocked;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Server-to-server push from the auction engine: no session, no CSRF.
            // It authenticates with a bearer key inside the controller.
            Route::match(['get', 'post'], 'webhooks/auction', AuctionWebhookController::class)
                ->name('webhooks.auction');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->alias(['phone.verified' => EnsurePhoneIsVerified::class]);

        $middleware->web(append: [
            HandleAppearance::class,
            EnsureUserIsNotBlocked::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Our own error page for the marketplace. The admin panel, the engine's
        // webhook and JSON clients keep Laravel's default responses, and so does
        // a 500 while debugging, where the stack trace is the useful thing.
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            $status = $response->getStatusCode();

            if ($request->is('admin', 'admin/*', 'livewire/*', 'webhooks/*', 'up')
                || ($request->expectsJson() && ! $request->hasHeader('X-Inertia'))) {
                return $response;
            }

            if ($status === 419) {
                return back()->with('status', 'Phiên làm việc đã hết hạn. Vui lòng thử lại.');
            }

            if (in_array($status, [403, 404, 429, 503], true) || ($status === 500 && ! config('app.debug'))) {
                return Inertia::render('ErrorPage', ['status' => $status])
                    ->toResponse($request)
                    ->setStatusCode($status);
            }

            return $response;
        });
    })->create();
