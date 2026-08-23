<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Application\Jobs;

use App\Modules\Integrations\Application\Services\IntegrationHealthService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CheckIntegrationHealthJob implements ShouldQueue
{
    use Queueable;

    public function handle(IntegrationHealthService $service): void
    {
        $service->renewExpiringEverywhere();
    }
}
