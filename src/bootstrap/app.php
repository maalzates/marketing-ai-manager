<?php

use App\Modules\Core\Domain\Exceptions\ApiException;
use App\Modules\Core\Presentation\Http\Middleware\EnsureAccountContext;
use App\Modules\Core\Presentation\Http\Middleware\EnsureRole;
use App\Modules\Core\Presentation\Http\Responses\ExceptionRenderer;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // TLS terminates at Cloudflare and the hop to this origin is plain HTTP, so the
        // visitor's real IP and scheme only exist in headers. Without trusting them Laravel
        // builds http:// URLs behind an https:// site, which the browser blocks as mixed
        // content, and every rate limit and audit-log entry records Cloudflare's IP instead
        // of the user's. Trusting every proxy is safe *because* the firewall only lets
        // Cloudflare reach port 80 — if that restriction is lifted, this becomes spoofable.
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO);

        $middleware->alias([
            'account' => EnsureAccountContext::class,
            'role' => EnsureRole::class,
        ]);

        // Registering a limiter does not apply it. Every API route is throttled here so a
        // new route cannot ship unlimited by omission; `chat` and `admin` tighten it further
        // on their own groups. The limits themselves are read per request from the settings
        // registry, so an admin can change them without a deploy.
        $middleware->api(append: ['throttle:api']);
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
        // failure came from a service or from the router. The return type is the Symfony
        // response rather than a JsonResponse because `/media/{token}` lives outside /api and
        // answers a media fetcher with a bare status and no body.
        $exceptions->render(
            fn (Throwable $exception, Request $request): ?Response => app(ExceptionRenderer::class)
                ->render($exception, $request)
        );
    })->create();
