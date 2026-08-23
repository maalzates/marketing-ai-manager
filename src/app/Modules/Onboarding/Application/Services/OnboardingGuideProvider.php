<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Application\Services;

use App\Modules\Integrations\Domain\Enums\IntegrationProvider;
use App\Modules\Knowledge\Application\Services\KnowledgeService;
use App\Modules\Knowledge\Domain\Enums\KnowledgeType;
use App\Modules\Knowledge\Infrastructure\Persistence\KnowledgeEntry;
use Illuminate\Support\Collection;

/**
 * The wizard is guide-first, so the guides are fetched as a set rather than one per step:
 * a missing or unpublished entry then leaves that step without its guide instead of
 * failing the whole screen.
 */
readonly class OnboardingGuideProvider
{
    public function __construct(private KnowledgeService $knowledge) {}

    /**
     * Deliberately a base Collection: Eloquent's own overrides `only()` to filter by primary
     * key, which would silently return nothing for a set keyed by the entry's `key`.
     *
     * @return Collection<string, KnowledgeEntry>
     */
    public function all(string $locale = KnowledgeService::DEFAULT_LOCALE): Collection
    {
        return collect($this->knowledge->listByType(KnowledgeType::OnboardingGuide, $locale)->all())
            ->keyBy('key');
    }

    public function docsUrl(IntegrationProvider $provider, string $locale = KnowledgeService::DEFAULT_LOCALE): ?string
    {
        return $this->all($locale)
            ->first(fn (KnowledgeEntry $entry): bool => data_get($entry->metadata, 'provider') === $provider->value)
            ?->metadata['docs_url'] ?? null;
    }
}
