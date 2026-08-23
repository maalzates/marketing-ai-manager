<?php

declare(strict_types=1);

namespace App\Modules\Content\Domain\Contracts;

use App\Modules\Experiments\Domain\Enums\ExperimentPlatform;
use Illuminate\Support\Collection;

interface ChannelProviderRegistryInterface
{
    public function for(ExperimentPlatform $platform): ?ChannelProviderInterface;

    /** @return Collection<int, ChannelProviderInterface> */
    public function all(): Collection;
}
