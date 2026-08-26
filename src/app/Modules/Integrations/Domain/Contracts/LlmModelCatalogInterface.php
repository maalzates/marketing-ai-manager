<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Domain\Contracts;

use App\Modules\Integrations\Domain\Enums\IntegrationProvider;

/**
 * The contract lives here, in the module that asks the question, because `Ai` already
 * depends on `Integrations` for the credential: owning it the other way round would close
 * a cycle. `Ai` implements it, and stays the only place that knows what a model costs.
 *
 * The account matters: `GET /models` answers what that organisation is entitled to call, so
 * two accounts of this application can legitimately see different catalogues.
 */
interface LlmModelCatalogInterface
{
    /**
     * @return list<array{id: string, input: float|null, output: float|null, state: string}>
     *                                                                                       empty for providers that are not an LLM
     */
    public function forProvider(IntegrationProvider $provider, int $accountId): array;

    /** Where a human reads the tariff, since no provider serves it over the API. */
    public function pricingUrlFor(IntegrationProvider $provider): ?string;
}
