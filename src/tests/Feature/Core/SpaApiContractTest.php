<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Models\User;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Ai\Domain\Contracts\LlmClientFactoryInterface;
use App\Modules\Ai\Domain\Enums\LlmProvider;
use App\Modules\Ai\Infrastructure\Clients\FakeLlmClient;
use App\Modules\Ai\Infrastructure\Clients\FakeLlmClientFactory;
use App\Modules\Brands\Infrastructure\Persistence\BrandProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * These tests read the JavaScript source on purpose: the two 422s that made the new-strategy
 * screen unusable lived in the gap between the field names the SPA sends and the ones the API
 * validates, and nothing that only reads PHP can see that gap.
 */
class SpaApiContractTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = Account::factory()->create();
        $user = User::factory()->create();
        $this->account->users()->attach($user);

        Sanctum::actingAs($user);
    }

    public function test_the_new_strategy_form_sends_every_field_the_api_requires(): void
    {
        $brandProfile = BrandProfile::factory()->create(['account_id' => $this->account->id]);

        $payload = $this->payloadFrom(
            'resources/js/pages/StrategiesPage.vue',
            'const form = reactive({',
            [
                'brand_profile_id' => $brandProfile->id,
                'name' => 'Captación local',
                'objective' => 'Conseguir 30 leads al mes en el barrio.',
                'north_star_metric' => 'cpl',
                'monthly_budget' => 1200,
            ],
        );

        $this->postJson('/api/v1/strategies', $payload)->assertCreated();

        $this->assertDatabaseHas('strategies', [
            'account_id' => $this->account->id,
            'name' => 'Captación local',
        ]);
    }

    public function test_the_ask_ai_button_sends_every_field_the_api_requires(): void
    {
        $this->app->instance(LlmClientFactoryInterface::class, new FakeLlmClientFactory(
            static fn (int $accountId, LlmProvider $provider) => FakeLlmClient::replaying(
                $provider,
                'anthropic-structured-suggestion.json',
            ),
        ));

        $payload = $this->payloadFrom(
            'resources/js/stores/ai.js',
            'requestSuggestion({',
            [
                'target' => 'objetivo de la estrategia',
                // The name the store used to send. Kept so a regression fails on the 422 it
                // causes instead of on an unresolved field name.
                'field' => 'objetivo de la estrategia',
                'context' => ['brand' => 'Panadería del barrio'],
            ],
        );

        $this->postJson('/api/v1/ai/suggest', $payload)
            ->assertOk()
            ->assertJsonPath('result.value', 'Impulsar reservas de cenas entre semana');
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function payloadFrom(string $relativePath, string $declaration, array $values): array
    {
        $keys = $this->objectKeysIn($relativePath, $declaration);

        $this->assertSame(
            [],
            array_values(array_diff($keys, array_keys($values))),
            "«{$relativePath}» manda campos para los que este test no tiene un valor realista: añádelos.",
        );

        return array_intersect_key($values, array_flip($keys));
    }

    /**
     * The keys of the object literal that `$declaration` opens — `{ a: 1, b }` gives `a`, `b`.
     *
     * @return list<string>
     */
    private function objectKeysIn(string $relativePath, string $declaration): array
    {
        $source = (string) file_get_contents(base_path($relativePath));
        $position = strpos($source, $declaration);

        $this->assertNotFalse(
            $position,
            "No se encontró «{$declaration}» en {$relativePath}. Si el fuente se refactorizó, este test se actualiza, no se borra.",
        );

        $keys = [];

        foreach (explode(',', (string) strstr(substr($source, $position + strlen($declaration)), '}', true)) as $part) {
            $key = trim(explode(':', $part)[0]);

            if ($key !== '') {
                $keys[] = $key;
            }
        }

        $this->assertNotEmpty($keys, "«{$declaration}» en {$relativePath} no declara ningún campo.");

        return $keys;
    }
}
