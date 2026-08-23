<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Models\User;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Audit\Application\DTO\RecordActionDTO;
use App\Modules\Audit\Application\Services\ActionLogService;
use App\Modules\Audit\Domain\Enums\ActionOrigin;
use App\Modules\Audit\Infrastructure\Persistence\ActionLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The masker has no door of its own: it runs inside the action log, which is where the
 * guarantee that matters lives — a credential must never reach the audit table in clear.
 */
class SecretMaskingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_recorded_credential_keeps_only_its_last_four_characters(): void
    {
        $this->record([
            'api_key' => 'sk-ant-api03-abcdefgh1234',
            'access_token' => 'ya29.a0AfH6SMBqrstuvwx5678',
            'client_secret' => 'GOCSPX-zyxwvutsrq9012',
            'password' => 'correct-horse-battery-3456',
        ]);

        // MySQL reorders the keys of a JSON object, so the comparison cannot be positional.
        $this->assertEqualsCanonicalizing([
            'api_key' => '****1234',
            'access_token' => '****5678',
            'client_secret' => '****9012',
            'password' => '****3456',
        ], ActionLog::query()->sole()->payload);
    }

    public function test_the_clear_credential_never_reaches_the_stored_row(): void
    {
        $this->record(['api_key' => 'sk-ant-api03-abcdefgh1234']);

        $this->assertStringNotContainsString(
            'sk-ant-api03-abcdefgh',
            (string) ActionLog::query()->sole()->getRawOriginal('payload'),
        );
    }

    public function test_a_credential_too_short_to_split_reveals_nothing(): void
    {
        $this->record(['api_key' => 'abc123']);

        $this->assertSame(['api_key' => '****'], ActionLog::query()->sole()->payload);
    }

    public function test_a_credential_nested_inside_the_payload_is_masked_too(): void
    {
        $this->record(['provider' => 'meta', 'grant' => ['access_token' => 'EAAG1234567890abcd']]);

        $this->assertSame('****abcd', ActionLog::query()->sole()->payload['grant']['access_token']);
    }

    /** A sensitive key holding a whole bag of values is redacted wholesale, not walked into. */
    public function test_a_branch_whose_own_key_is_sensitive_collapses_to_a_mask(): void
    {
        $this->record(['credentials' => ['access_token' => 'EAAG1234567890abcd']]);

        $this->assertSame(['credentials' => '****'], ActionLog::query()->sole()->payload);
    }

    public function test_a_field_that_is_not_a_credential_is_stored_unchanged(): void
    {
        $this->record(['name' => 'Q4 growth push', 'api_key' => 'sk-ant-api03-abcdefgh1234']);

        $this->assertSame('Q4 growth push', ActionLog::query()->sole()->payload['name']);
    }

    private function record(array $payload): void
    {
        $account = Account::factory()->create();
        $user = User::factory()->create();

        app(ActionLogService::class)->record(new RecordActionDTO(
            (int) $account->id,
            (int) $user->id,
            'integration.connected',
            ActionOrigin::UI,
            $payload,
        ));
    }
}
