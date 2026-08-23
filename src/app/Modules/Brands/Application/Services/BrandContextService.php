<?php

declare(strict_types=1);

namespace App\Modules\Brands\Application\Services;

/**
 * The brand block injected into every LLM call. It is the stable head of the cacheable
 * prompt prefix, so a key is added here only when every prompt needs it.
 */
readonly class BrandContextService
{
    public function __construct(private BrandProfileService $profiles) {}

    /**
     * @return array<string, mixed>
     */
    public function promptContext(int $accountId, int $brandProfileId): array
    {
        $profile = $this->profiles->find($brandProfileId, $accountId);

        return [
            'name' => $profile->name,
            'kind' => $profile->kind->value,
            'description' => $profile->description,
            'niche' => $profile->niche,
            'value_proposition' => $profile->value_proposition,
            'tone_of_voice' => $profile->tone_of_voice,
            'values' => $profile->values,
            'banned_topics' => $profile->banned_topics,
            'buyer_personas' => $profile->buyer_personas,
        ];
    }
}
