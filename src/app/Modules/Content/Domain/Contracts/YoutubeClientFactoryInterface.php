<?php

declare(strict_types=1);

namespace App\Modules\Content\Domain\Contracts;

use App\Modules\Content\Infrastructure\Clients\YoutubeContentClient;

interface YoutubeClientFactoryInterface
{
    public function forAccount(int $accountId): YoutubeContentClient;
}
