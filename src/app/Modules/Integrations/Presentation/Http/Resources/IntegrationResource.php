<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Presentation\Http\Resources;

use App\Modules\Integrations\Infrastructure\Persistence\Integration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Integration
 *
 * Whitelists the fields that leave the server. The stored credential is never one of
 * them — only the last four characters of an API key, so the user can tell which key is
 * configured without the value ever travelling back over the wire.
 */
class IntegrationResource extends JsonResource
{
    private const int MINIMUM_LENGTH_TO_REVEAL_SUFFIX = 8;

    public function toArray(Request $request): array
    {
        return [
            'provider' => $this->provider->value,
            'kind' => $this->kind->value,
            'status' => $this->status->value,
            'masked_key' => self::mask($this->credentials['api_key'] ?? null),
            'external_account_id' => $this->external_account_id,
            'scopes' => $this->scopes,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'last_checked_at' => $this->last_checked_at?->toIso8601String(),
            'failure_count' => $this->failure_count,
        ];
    }

    private static function mask(?string $apiKey): ?string
    {
        return match (true) {
            $apiKey === null => null,
            strlen($apiKey) >= self::MINIMUM_LENGTH_TO_REVEAL_SUFFIX => '****'.substr($apiKey, -4),
            default => '****',
        };
    }
}
