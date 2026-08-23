<?php

declare(strict_types=1);

namespace App\Modules\Knowledge;

use App\Modules\Knowledge\Domain\Contracts\KnowledgeEntryRepositoryInterface;
use App\Modules\Knowledge\Infrastructure\Repositories\KnowledgeEntryRepository;
use Illuminate\Support\ServiceProvider;

class KnowledgeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(KnowledgeEntryRepositoryInterface::class, KnowledgeEntryRepository::class);
    }
}
