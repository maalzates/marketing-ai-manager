<?php

declare(strict_types=1);

namespace App\Modules\Content\Infrastructure\Providers;

use App\Modules\Content\Domain\Contracts\ChannelProviderInterface;
use App\Modules\Content\Domain\Contracts\ChannelProviderRegistryInterface;
use App\Modules\Experiments\Domain\Enums\ExperimentPlatform;
use Illuminate\Support\Collection;

/**
 * The only place that maps a platform to its integration. Everything above it works with
 * ChannelProviderInterface, so adding TikTok is a line here and a new provider class.
 */
readonly class ChannelProviderRegistry implements ChannelProviderRegistryInterface
{
    /** @param  list<ChannelProviderInterface>  $providers */
    public function __construct(private array $providers) {}

    public function for(ExperimentPlatform $platform): ?ChannelProviderInterface
    {
        return collect($this->providers)
            ->first(fn (ChannelProviderInterface $provider): bool => $provider->platform() === $platform);
    }

    public function all(): Collection
    {
        return collect($this->providers);
    }
}
