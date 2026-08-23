<?php

declare(strict_types=1);

namespace App\Modules\Core\Presentation\Http\Middleware;

use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Domain\Exceptions\AccountContextException;
use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

readonly class EnsureAccountContext
{
    public function __construct(private Container $container) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $account = $user?->accounts()->first() ?? throw AccountContextException::missingAccount((int) $user?->id);

        if (! $account->is_active) {
            throw AccountContextException::inactiveAccount((int) $account->id);
        }

        $this->container->instance(
            AccountContext::class,
            new AccountContext((int) $account->id, (int) $user->id),
        );

        return $next($request);
    }
}
