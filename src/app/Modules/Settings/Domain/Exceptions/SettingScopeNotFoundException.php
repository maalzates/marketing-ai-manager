<?php

declare(strict_types=1);

namespace App\Modules\Settings\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use App\Modules\Settings\Domain\Enums\SettingScope;
use Symfony\Component\HttpFoundation\Response;

/**
 * Not found rather than forbidden: telling the caller that a scope exists but belongs to
 * somebody else is itself the leak.
 */
class SettingScopeNotFoundException extends ClientException
{
    public static function forScope(SettingScope $scope, int $scopeId): self
    {
        $exception = new self("The requested {$scope->value} was not found.", Response::HTTP_NOT_FOUND);
        $exception->context = ['scope' => $scope->value, 'scope_id' => $scopeId];

        return $exception;
    }
}
