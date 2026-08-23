<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Jobs;

use App\Modules\Content\Application\DTO\GenerateScriptsDTO;
use App\Modules\Content\Application\Services\ContentPlanService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateContentScriptsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly GenerateScriptsDTO $request) {}

    public function handle(ContentPlanService $service): void
    {
        $service->generate($this->request);
    }
}
