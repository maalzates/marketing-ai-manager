<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Integrations\Domain\Enums\IntegrationKind;
use App\Modules\Integrations\Domain\Enums\IntegrationProvider;
use App\Modules\Integrations\Domain\Enums\IntegrationStatus;
use App\Modules\Integrations\Infrastructure\Persistence\Integration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Integration>
 */
class IntegrationFactory extends Factory
{
    protected $model = Integration::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'provider' => IntegrationProvider::ANTHROPIC,
            'kind' => IntegrationKind::API_KEY,
            'credentials' => ['api_key' => 'sk-ant-'.fake()->regexify('[A-Za-z0-9]{40}')],
            'status' => IntegrationStatus::CONNECTED,
            'external_account_id' => null,
            'scopes' => null,
            'expires_at' => null,
            'last_checked_at' => now(),
            'last_error' => null,
            'failure_count' => 0,
        ];
    }

    public function anthropic(): static
    {
        return $this->apiKey(IntegrationProvider::ANTHROPIC, 'sk-ant-');
    }

    public function openAi(): static
    {
        return $this->apiKey(IntegrationProvider::OPENAI, 'sk-proj-');
    }

    public function gemini(): static
    {
        return $this->apiKey(IntegrationProvider::GEMINI, 'AIza');
    }

    public function apify(): static
    {
        return $this->apiKey(IntegrationProvider::APIFY, 'apify_api_');
    }

    public function google(): static
    {
        return $this->googleGrant(IntegrationProvider::GOOGLE, [
            ...config('services.google.login_scopes'),
            ...config('services.google.drive_scopes'),
        ]);
    }

    public function youtube(): static
    {
        return $this->googleGrant(IntegrationProvider::YOUTUBE, config('services.google.youtube_scopes'));
    }

    public function meta(): static
    {
        return $this->state(fn (array $attributes): array => [
            'provider' => IntegrationProvider::META,
            'kind' => IntegrationKind::OAUTH,
            'credentials' => [
                'access_token' => 'EAA'.fake()->regexify('[A-Za-z0-9]{60}'),
                'token_type' => 'bearer',
            ],
            'scopes' => config('services.meta.scopes'),
            'external_account_id' => (string) fake()->numerify('################'),
            'expires_at' => now()->addDays(59),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => IntegrationStatus::EXPIRED,
            'expires_at' => now()->subDay(),
        ]);
    }

    public function expiringSoon(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => IntegrationStatus::CONNECTED,
            'expires_at' => now()->addDay(),
        ]);
    }

    public function errored(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => IntegrationStatus::ERROR,
            'last_error' => '{"status":401,"body":null}',
            'failure_count' => 1,
        ]);
    }

    public function disconnected(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => IntegrationStatus::DISCONNECTED,
            'last_checked_at' => null,
        ]);
    }

    private function apiKey(IntegrationProvider $provider, string $prefix): static
    {
        return $this->state(fn (array $attributes): array => [
            'provider' => $provider,
            'kind' => IntegrationKind::API_KEY,
            'credentials' => ['api_key' => $prefix.fake()->regexify('[A-Za-z0-9]{40}')],
            'scopes' => null,
            'expires_at' => null,
        ]);
    }

    /** @param  list<string>  $scopes */
    private function googleGrant(IntegrationProvider $provider, array $scopes): static
    {
        return $this->state(fn (array $attributes): array => [
            'provider' => $provider,
            'kind' => IntegrationKind::OAUTH,
            'credentials' => [
                'access_token' => 'ya29.'.fake()->regexify('[A-Za-z0-9_\-]{60}'),
                'refresh_token' => '1//0'.fake()->regexify('[A-Za-z0-9_\-]{60}'),
                'token_type' => 'Bearer',
            ],
            'scopes' => $scopes,
            'external_account_id' => (string) fake()->numerify('####################'),
            'expires_at' => now()->addHour(),
        ]);
    }
}
