<?php

declare(strict_types=1);

namespace App\Modules\Audit;

use App\Modules\Audit\Domain\Contracts\ActionLogRepositoryInterface;
use App\Modules\Audit\Domain\Contracts\ApifyUsageLogRepositoryInterface;
use App\Modules\Audit\Domain\Contracts\LlmUsageLogRepositoryInterface;
use App\Modules\Audit\Infrastructure\Repositories\ActionLogRepository;
use App\Modules\Audit\Infrastructure\Repositories\ApifyUsageLogRepository;
use App\Modules\Audit\Infrastructure\Repositories\LlmUsageLogRepository;
use Illuminate\Support\ServiceProvider;

class AuditServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ActionLogRepositoryInterface::class, ActionLogRepository::class);
        $this->app->bind(LlmUsageLogRepositoryInterface::class, LlmUsageLogRepository::class);
        $this->app->bind(ApifyUsageLogRepositoryInterface::class, ApifyUsageLogRepository::class);
    }
}
