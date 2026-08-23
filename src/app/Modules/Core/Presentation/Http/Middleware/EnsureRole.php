<?php

declare(strict_types=1);

namespace App\Modules\Core\Presentation\Http\Middleware;

use App\Modules\Core\Domain\Exceptions\RoleNotAllowedException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

readonly class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        return collect($roles)->contains(fn (string $role): bool => $request->user()?->hasRole($role) === true)
            ? $next($request)
            : throw RoleNotAllowedException::forRoles($roles);
    }
}
