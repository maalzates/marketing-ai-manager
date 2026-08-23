<?php

declare(strict_types=1);

namespace App\Modules\Admin\Domain\Support;

use App\Modules\Admin\Infrastructure\Persistence\ApplicationApiKey;

/**
 * The one moment the plaintext token exists outside the caller's clipboard. It is never
 * persisted, never logged and never attached to an exception; it travels from the service
 * to the creation response and dies with the request.
 */
readonly class IssuedApiKey
{
    public function __construct(public ApplicationApiKey $key, public string $plainToken) {}
}
