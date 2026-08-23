<?php

declare(strict_types=1);

namespace App\Modules\Content\Domain\Contracts;

use App\Modules\Content\Infrastructure\Clients\InstagramClient;

interface InstagramClientFactoryInterface
{
    public function forAccount(int $accountId): InstagramClient;
}
