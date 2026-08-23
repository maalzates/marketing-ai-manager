<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Models\User;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Ai\Domain\Contracts\LlmClientFactoryInterface;
use App\Modules\Ai\Domain\Enums\AiTask;
use App\Modules\Ai\Domain\Enums\LlmProvider;
use App\Modules\Ai\Infrastructure\Clients\FakeLlmClient;
use App\Modules\Ai\Infrastructure\Clients\FakeLlmClientFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * "Ask AI" behind every field in the product. A suggestion is a draft the user edits, so
 * the one thing that must never happen is a half-filled answer arriving as if it were
 * whole and being written into a brand profile.
 */
class FieldSuggestionTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = $this->actAsMemberOfANewAccount();
    }

    public function test_a_suggestion_comes_back_as_a_value_the_user_can_paste(): void
    {
        $this->replaying('anthropic-structured-suggestion.json');

        $this->suggest()
            ->assertOk()
            ->assertJsonPath('result.value', 'Impulsar reservas de cenas entre semana')
            ->assertJsonPath('result.rationale', 'El historial muestra ocupacion baja de martes a jueves.')
            ->assertJsonPath('result.alternatives.0', 'Aumentar el ticket medio');
    }

    public function test_an_answer_missing_a_required_field_is_refused_rather_than_returned_half_filled(): void
    {
        $this->replaying('anthropic-structured-missing-field.json');

        $response = $this->suggest()
            ->assertStatus(Response::HTTP_BAD_GATEWAY)
            ->assertJsonPath('errors.message', 'The model returned an answer that does not match the expected structure. Try again.');

        $this->assertStringNotContainsString('Impulsar reservas', $response->getContent());
    }

    public function test_an_answer_that_is_not_json_is_refused(): void
    {
        $this->replaying('anthropic-text.json');

        $this->suggest()
            ->assertStatus(Response::HTTP_BAD_GATEWAY)
            ->assertJsonPath('errors.message', 'The model returned an answer that does not match the expected structure. Try again.');
    }

    public function test_the_call_is_billed_to_the_calling_account(): void
    {
        $this->replaying('anthropic-structured-suggestion.json');

        $this->suggest()->assertOk();

        $this->assertDatabaseHas('llm_usage_logs', [
            'account_id' => $this->account->id,
            'feature' => AiTask::FieldSuggestion->value,
        ]);
    }

    public function test_a_suggestion_without_a_target_field_is_rejected_by_validation(): void
    {
        $this->replaying('anthropic-structured-suggestion.json');

        $this->postJson('/api/v1/ai/suggest', ['task' => AiTask::FieldSuggestion->value])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonPath('errors.fields.target.0', 'The target field is required.');
    }

    public function test_a_task_the_application_does_not_declare_is_rejected_by_validation(): void
    {
        $this->replaying('anthropic-structured-suggestion.json');

        $this->postJson('/api/v1/ai/suggest', ['task' => 'write_my_thesis', 'target' => 'objetivo'])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonPath('errors.fields.task.0', 'The selected task is invalid.');
    }

    public function test_asking_for_a_suggestion_requires_authentication(): void
    {
        app('auth')->forgetGuards();

        $this->postJson('/api/v1/ai/suggest', ['target' => 'objetivo'])->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    private function suggest(): TestResponse
    {
        return $this->postJson('/api/v1/ai/suggest', [
            'task' => AiTask::FieldSuggestion->value,
            'target' => 'objetivo de la estrategia',
        ]);
    }

    private function replaying(string $fixture): void
    {
        $this->app->instance(LlmClientFactoryInterface::class, new FakeLlmClientFactory(
            static fn (int $accountId, LlmProvider $provider) => FakeLlmClient::replaying($provider, $fixture),
        ));
    }

    private function actAsMemberOfANewAccount(): Account
    {
        $account = Account::factory()->create();
        $user = User::factory()->create();
        $user->accounts()->attach($account);

        Sanctum::actingAs($user);

        return $account;
    }
}
