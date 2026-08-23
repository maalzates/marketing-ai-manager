<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Domain\Contracts;

use App\Modules\Integrations\Infrastructure\Clients\AnthropicVerificationClient;
use App\Modules\Integrations\Infrastructure\Clients\ApifyVerificationClient;
use App\Modules\Integrations\Infrastructure\Clients\GeminiVerificationClient;
use App\Modules\Integrations\Infrastructure\Clients\OpenAiVerificationClient;

/**
 * Builds a client around one account's key and hands it over. Nothing here is cached:
 * a client that outlived the operation would answer the next account with this key.
 */
interface VerificationClientFactoryInterface
{
    public function anthropic(string $apiKey): AnthropicVerificationClient;

    public function openAi(string $apiKey): OpenAiVerificationClient;

    public function gemini(string $apiKey): GeminiVerificationClient;

    public function apify(string $apiKey): ApifyVerificationClient;
}
