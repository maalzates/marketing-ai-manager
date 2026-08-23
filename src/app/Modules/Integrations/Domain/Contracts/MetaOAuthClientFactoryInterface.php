<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Domain\Contracts;

use App\Modules\Integrations\Infrastructure\Clients\MetaOAuthClient;

interface MetaOAuthClientFactoryInterface
{
    public function create(): MetaOAuthClient;
}
