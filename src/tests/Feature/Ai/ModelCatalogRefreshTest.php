<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Models\User;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Ai\Application\Services\LlmModelCatalog;
use App\Modules\Integrations\Domain\Enums\IntegrationProvider;
use App\Modules\Integrations\Infrastructure\Persistence\Integration;
use App\Modules\Settings\Infrastructure\Persistence\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\EmulatesASecondRequest;
use Tests\Support\FakeTransport;
use Tests\TestCase;

/**
 * The model list comes from the providers; the price does not, because none of the three
 * serves one. What this covers is the seam between those two facts.
 */
class ModelCatalogRefreshTest extends TestCase
{
    use EmulatesASecondRequest, RefreshDatabase;

    private Account $account;

    private FakeTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transport = FakeTransport::silent()->install($this->app);
        $this->account = Account::factory()->create();

        $user = User::factory()->create();
        $user->accounts()->attach($this->account);
        Sanctum::actingAs($user);
    }

    public function test_the_catalogue_falls_back_to_the_priced_list_when_it_was_never_refreshed(): void
    {
        Integration::factory()->openAi()->for($this->account)->create();

        $models = $this->models(IntegrationProvider::OPENAI);

        $this->assertNotEmpty($models);
        $this->assertSame([LlmModelCatalog::AVAILABLE], collect($models)->pluck('state')->unique()->all());
    }

    public function test_openai_models_are_read_from_its_unpaginated_list(): void
    {
        Integration::factory()->openAi()->for($this->account)->create();
        $this->transport->queue(FakeTransport::json(['data' => [
            ['id' => 'gpt-4.1-nano'],
            ['id' => 'o9-preview'],
        ]]));

        $this->postJson('/api/v1/ai/models/refresh')->assertOk()->assertJsonPath('result.openai', 2);

        $models = collect($this->models(IntegrationProvider::OPENAI))->keyBy('id');

        $this->assertSame(LlmModelCatalog::AVAILABLE, $models['gpt-4.1-nano']['state']);
        $this->assertSame(LlmModelCatalog::UNPRICED, $models['o9-preview']['state']);
        $this->assertNull($models['o9-preview']['input']);
    }

    /** A model this deployment prices but the provider stopped listing is not callable. */
    public function test_a_priced_model_the_provider_no_longer_lists_is_marked_retired(): void
    {
        Integration::factory()->openAi()->for($this->account)->create();
        $this->transport->queue(FakeTransport::json(['data' => [['id' => 'gpt-4.1-nano']]]));

        $this->postJson('/api/v1/ai/models/refresh')->assertOk();

        $models = collect($this->models(IntegrationProvider::OPENAI))->keyBy('id');

        $this->assertSame(LlmModelCatalog::RETIRED, $models['gpt-5.6-sol']['state']);
        $this->assertEquals(20.0, $models['gpt-5.6-sol']['output']);
    }

    public function test_anthropic_pagination_is_followed_to_the_end(): void
    {
        Integration::factory()->anthropic()->for($this->account)->create();
        $this->transport->queue(
            FakeTransport::json(['data' => [['id' => 'claude-opus-5']], 'has_more' => true, 'last_id' => 'claude-opus-5']),
            FakeTransport::json(['data' => [['id' => 'claude-sonnet-5']], 'has_more' => false, 'last_id' => 'claude-sonnet-5']),
        );

        $this->postJson('/api/v1/ai/models/refresh')->assertOk()->assertJsonPath('result.anthropic', 2);

        $this->assertSame(2, $this->transport->requestCount());
    }

    public function test_gemini_drops_models_that_cannot_hold_a_conversation(): void
    {
        Integration::factory()->gemini()->for($this->account)->create();
        $this->transport->queue(FakeTransport::json(['models' => [
            ['name' => 'models/gemini-3.7-flash', 'supportedGenerationMethods' => ['generateContent']],
            ['name' => 'models/text-embedding-004', 'supportedGenerationMethods' => ['embedContent']],
        ]]));

        $this->postJson('/api/v1/ai/models/refresh')->assertOk()->assertJsonPath('result.gemini', 1);

        $ids = collect($this->models(IntegrationProvider::GEMINI))->pluck('id');

        $this->assertTrue($ids->contains('gemini-3.7-flash'));
        $this->assertFalse($ids->contains('text-embedding-004'));
    }

    /** A provider having a bad minute must not take the model selector away from the user. */
    public function test_a_provider_that_fails_keeps_its_last_known_list(): void
    {
        Integration::factory()->openAi()->for($this->account)->create();
        $this->transport->queue(FakeTransport::json(['data' => [['id' => 'gpt-4.1-nano']]]));
        $this->postJson('/api/v1/ai/models/refresh')->assertOk();

        $this->betweenRequests();
        $this->transport->queue(FakeTransport::json(['error' => ['message' => 'down']], 500));
        $this->postJson('/api/v1/ai/models/refresh')->assertOk()->assertJsonPath('result.openai', 0);

        $models = collect($this->models(IntegrationProvider::OPENAI))->keyBy('id');

        $this->assertSame(LlmModelCatalog::AVAILABLE, $models['gpt-4.1-nano']['state']);
    }

    public function test_a_provider_without_a_credential_is_never_asked(): void
    {
        $this->postJson('/api/v1/ai/models/refresh')->assertOk()->assertExactJson(['result' => [], 'errors' => []]);

        $this->assertSame(0, $this->transport->requestCount());
    }

    /**
     * A model the provider lists and this deployment never priced is callable. It records at
     * zero rather than being refused: prices live on a web page, and refusing would make the
     * live catalogue decorative.
     */
    public function test_a_model_without_a_tariff_is_callable_and_recorded_at_zero(): void
    {
        Integration::factory()->anthropic()->for($this->account)->create();
        // Written before the first request of the test: the settings cascade is cached per
        // request, so a setting created after a warm read would never be seen.
        Setting::factory()->forAccount($this->account->id)->create([
            'key' => 'ai.models.per_task.field_suggestion',
            'value' => 'claude-tomorrow-1',
        ]);
        $this->transport->queue(FakeTransport::json(['data' => [['id' => 'claude-tomorrow-1']]]));
        $this->postJson('/api/v1/ai/models/refresh')->assertOk();

        $this->betweenRequests();
        $this->transport->queue(FakeTransport::fixture('anthropic-structured-suggestion.json'));

        $this->postJson('/api/v1/ai/suggest', ['task' => 'field_suggestion', 'target' => 'objetivo'])->assertOk();

        $this->assertDatabaseHas('llm_usage_logs', [
            'account_id' => $this->account->id,
            'estimated_cost_usd' => '0.000000',
        ]);
    }

    public function test_each_provider_carries_a_link_to_where_its_prices_are_published(): void
    {
        $row = collect($this->getJson('/api/v1/integrations')->assertOk()->json('result'))
            ->firstWhere('provider', IntegrationProvider::OPENAI->value);

        $this->assertSame('https://openai.com/api/pricing/', $row['pricing_url']);
    }

    private function models(IntegrationProvider $provider): array
    {
        return collect($this->getJson('/api/v1/integrations')->assertOk()->json('result'))
            ->firstWhere('provider', $provider->value)['models'];
    }
}
