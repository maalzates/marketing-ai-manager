<?php

use App\Modules\Core\Domain\Exceptions\ApiException;
use App\Modules\Core\Presentation\Http\Responses\ExceptionRenderer;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Domain exceptions carry their own severity and context. Logging them here is what
        // lets repositories and clients throw without ever calling Log:: themselves.
        $exceptions->report(function (ApiException $exception): bool {
            Log::log($exception->getLogLevel(), $exception->getMessage(), $exception->getContext());

            return false;
        });

        // One renderer for every exception, so the error envelope is the same whether the
        // failure came from a service or from the router.
        $exceptions->render(
            fn (Throwable $exception, Request $request): ?JsonResponse => app(ExceptionRenderer::class)
                ->render($exception, $request)
        );
    })->create();
