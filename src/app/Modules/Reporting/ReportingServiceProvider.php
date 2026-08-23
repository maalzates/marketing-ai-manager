<?php

declare(strict_types=1);

namespace App\Modules\Reporting;

use App\Modules\Reporting\Domain\Contracts\ReportRepositoryInterface;
use App\Modules\Reporting\Infrastructure\Repositories\ReportRepository;
use Illuminate\Support\ServiceProvider;

class ReportingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ReportRepositoryInterface::class, ReportRepository::class);
    }
}
