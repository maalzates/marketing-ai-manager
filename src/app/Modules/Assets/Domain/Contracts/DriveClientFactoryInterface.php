<?php

declare(strict_types=1);

namespace App\Modules\Assets\Domain\Contracts;

use App\Modules\Assets\Infrastructure\Clients\DriveClient;

interface DriveClientFactoryInterface
{
    public function forAccount(int $accountId): DriveClient;
}
