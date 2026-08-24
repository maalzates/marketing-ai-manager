<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Models\User;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Integrations\Domain\Enums\IntegrationStatus;
use App\Modules\Integrations\Infrastructure\Persistence\Integration;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeTransport;
use Tests\TestCase;

/**
 * The wizard's promise is that a green tick means the credential was used, not typed. So a
 * step completes on a live answer and on nothing else, every step can be walked away from,
 * and the state survives the user closing the tab.
 */
class OnboardingWizardTest extends TestCase
{
    use RefreshDatabase;

    private const string ANTHROPIC_MODELS_BODY = '{"data":[{"type":"model","id":"claude-opus-5","display_name":"Claude Opus 5","created_at":"2026-05-14T00:00:00Z"}],"has_more":false}';

    private const string ANTHROPIC_BAD_KEY_BODY = '{"type":"error","error":{"type":"authentication_error","message":"invalid x-api-key"}}';

    private Account $account;

    private FakeTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-09-14 09:00:00'));

        $this->transport = FakeTransport::silent()->install($this->app);

        $user = User::factory()->create();
        $this->account = Account::factory()->create(['owner_user_id' => $user->id]);
        $user->accounts()->attach($this->account);

        Sanctum::actingAs($user);
    }

    public function test_a_fresh_account_starts_the_wizard_on_its_first_step(): void
    {
        $this->getJson('/api/v1/onboarding')
            ->assertOk()
            ->assertJsonPath('result.resume_step', 'llm')
            ->assertJsonPath('result.completed_at', null)
            ->assertJsonPath('result.steps.0.status', 'pending');
    }

    /** The wizard cannot render the LLM step without knowing its three providers and how each authenticates. */
    public function test_the_state_lists_every_provider_a_step_accepts_and_how_it_authenticates(): void
    {
        $this->getJson('/api/v1/onboarding')
            ->assertOk()
            ->assertJsonPath('result.steps.0.step', 'llm')
            ->assertJsonPath('result.steps.0.providers.0', ['value' => 'anthropic', 'label' => 'Anthropic', 'kind' => 'api_key'])
            ->assertJsonCount(3, 'result.steps.0.providers')
            ->assertJsonPath('result.steps.3.providers.0.kind', 'oauth');
    }

    public function test_a_step_completes_when_the_provider_answers(): void
    {
        Integration::factory()->anthropic()->for($this->account)->disconnected()->create();
        $this->transport->queue(FakeTransport::json(self::ANTHROPIC_MODELS_BODY));

        $this->postJson('/api/v1/onboarding/steps/llm/complete', ['provider' => 'anthropic'])
            ->assertOk()
            ->assertJsonPath('result.steps.0.status', 'completed');

        $this->assertSame(1, $this->transport->requestCount());
    }

    /** A key that is merely stored is not a connection: the tick has to survive a real call. */
    public function test_a_step_does_not_complete_when_the_provider_rejects_the_credential(): void
    {
        Integration::factory()->anthropic()->for($this->account)->disconnected()->create();
        $this->transport->queue(FakeTransport::json(self::ANTHROPIC_BAD_KEY_BODY, 401));

        $this->postJson('/api/v1/onboarding/steps/llm/complete', ['provider' => 'anthropic'])
            ->assertStatus(422);

        $this->getJson('/api/v1/onboarding')->assertJsonPath('result.steps.0.status', 'pending');
    }

    public function test_a_rejected_step_leaves_the_integration_marked_as_broken(): void
    {
        $integration = Integration::factory()->anthropic()->for($this->account)->create();
        $this->transport->queue(FakeTransport::json(self::ANTHROPIC_BAD_KEY_BODY, 401));

        $this->postJson('/api/v1/onboarding/steps/llm/complete', ['provider' => 'anthropic'])->assertStatus(422);

        $this->assertNotSame(IntegrationStatus::CONNECTED, $integration->fresh()->status);
    }

    public function test_every_step_can_be_skipped_without_touching_a_provider(): void
    {
        foreach (['llm', 'apify', 'meta', 'google'] as $step) {
            $this->postJson("/api/v1/onboarding/steps/{$step}/skip")->assertOk();
        }

        $this->getJson('/api/v1/onboarding')
            ->assertOk()
            ->assertJsonPath('result.steps.0.status', 'skipped')
            ->assertJsonPath('result.resume_step', null);

        $this->assertSame(0, $this->transport->requestCount());
    }

    public function test_the_wizard_resumes_on_the_first_step_still_pending(): void
    {
        $this->postJson('/api/v1/onboarding/steps/llm/skip')->assertOk();

        $this->getJson('/api/v1/onboarding')
            ->assertOk()
            ->assertJsonPath('result.resume_step', 'apify');
    }

    public function test_the_progress_survives_a_later_request(): void
    {
        $this->postJson('/api/v1/onboarding/steps/llm/skip')->assertOk();
        $this->postJson('/api/v1/onboarding/steps/apify/skip')->assertOk();

        $this->getJson('/api/v1/onboarding')
            ->assertOk()
            ->assertJsonPath('result.steps.0.status', 'skipped')
            ->assertJsonPath('result.steps.1.status', 'skipped')
            ->assertJsonPath('result.resume_step', 'meta');

        $this->assertDatabaseCount('onboarding_states', 1);
    }

    public function test_an_unknown_step_is_rejected_before_anything_is_written(): void
    {
        $this->postJson('/api/v1/onboarding/steps/telepatia/skip')
            ->assertStatus(422)
            ->assertJsonPath('errors.fields.step.0', fn (string $message): bool => $message !== '');

        $this->assertDatabaseCount('onboarding_states', 0);
    }

    /** The checklist reads what is connected now, so a credential revoked later shows as broken. */
    public function test_the_checklist_reports_a_completed_step_whose_credential_died_as_broken(): void
    {
        Integration::factory()->anthropic()->for($this->account)->disconnected()->create();
        $this->transport->queue(FakeTransport::json(self::ANTHROPIC_MODELS_BODY));
        $this->postJson('/api/v1/onboarding/steps/llm/complete', ['provider' => 'anthropic'])->assertOk();

        Integration::query()->where('account_id', $this->account->id)
            ->update(['status' => IntegrationStatus::ERROR]);

        $this->getJson('/api/v1/onboarding/checklist')
            ->assertOk()
            ->assertJsonPath('result.0.connected', false)
            ->assertJsonPath('result.0.broken', true);
    }

    public function test_one_accounts_wizard_never_reads_another_accounts_progress(): void
    {
        $this->postJson('/api/v1/onboarding/steps/llm/skip')->assertOk();

        $other = User::factory()->create();
        $otherAccount = Account::factory()->create(['owner_user_id' => $other->id]);
        $other->accounts()->attach($otherAccount);
        Sanctum::actingAs($other);

        $this->getJson('/api/v1/onboarding')
            ->assertOk()
            ->assertJsonPath('result.resume_step', 'llm')
            ->assertJsonPath('result.steps.0.status', 'pending');
    }
}
