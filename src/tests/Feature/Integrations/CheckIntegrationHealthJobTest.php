<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Integrations\Application\Jobs\CheckIntegrationHealthJob;
use App\Modules\Integrations\Domain\Enums\IntegrationStatus;
use App\Modules\Integrations\Infrastructure\Persistence\Integration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\FakeTransport;
use Tests\TestCase;

/**
 * The nightly sweep. It runs unattended, so the only thing standing between a dead Google
 * grant and a user discovering it when a report fails is what this job writes to the row.
 */
class CheckIntegrationHealthJobTest extends TestCase
{
    use RefreshDatabase;

    private const string REFRESHED_TOKEN_BODY = '{"access_token":"ya29.RENEWED-Tz70BzhT3Zg","expires_in":3920,"scope":"https://www.googleapis.com/auth/drive.file","token_type":"Bearer"}';

    private const string REVOKED_GRANT_BODY = '{"error":"invalid_grant","error_description":"Token has been expired or revoked."}';

    private FakeTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transport = FakeTransport::silent()->install($this->app);
    }

    public function test_a_grant_about_to_expire_is_renewed(): void
    {
        $integration = Integration::factory()->google()->expiringSoon()->create();
        $this->transport->queue(FakeTransport::json(self::REFRESHED_TOKEN_BODY));

        CheckIntegrationHealthJob::dispatch();

        $this->assertSame('ya29.RENEWED-Tz70BzhT3Zg', $integration->fresh()->credentials['access_token']);
        $this->assertSame(IntegrationStatus::CONNECTED, $integration->fresh()->status);
    }

    public function test_a_grant_the_provider_has_revoked_is_marked_expired(): void
    {
        $integration = Integration::factory()->google()->expiringSoon()->create();
        $this->transport->queue(FakeTransport::json(self::REVOKED_GRANT_BODY, Response::HTTP_BAD_REQUEST));

        CheckIntegrationHealthJob::dispatch();

        $this->assertSame(IntegrationStatus::EXPIRED, $integration->fresh()->status);
        $this->assertStringContainsString('refresh_rejected', (string) $integration->fresh()->last_error);
    }

    /** Meta has no refresh grant, so an expiring token can only be replaced by a new consent. */
    public function test_a_meta_token_about_to_expire_is_marked_expired_without_calling_meta(): void
    {
        $integration = Integration::factory()->meta()->create(['expires_at' => now()->addDay()]);

        CheckIntegrationHealthJob::dispatch();

        $this->assertSame(IntegrationStatus::EXPIRED, $integration->fresh()->status);
        $this->assertStringContainsString('provider_has_no_refresh_grant', (string) $integration->fresh()->last_error);
        $this->assertSame(0, $this->transport->requestCount());
    }

    public function test_a_grant_that_expires_beyond_the_horizon_is_left_alone(): void
    {
        $integration = Integration::factory()->google()->create(['expires_at' => now()->addDays(30)]);

        CheckIntegrationHealthJob::dispatch();

        $this->assertSame(IntegrationStatus::CONNECTED, $integration->fresh()->status);
        $this->assertSame(0, $this->transport->requestCount());
    }

    public function test_a_grant_already_marked_expired_is_not_retried(): void
    {
        Integration::factory()->google()->expired()->create();

        CheckIntegrationHealthJob::dispatch();

        $this->assertSame(0, $this->transport->requestCount());
    }

    public function test_an_account_with_no_oauth_integrations_costs_no_request(): void
    {
        $integration = Integration::factory()->anthropic()->for(Account::factory()->create())->create();

        CheckIntegrationHealthJob::dispatch();

        $this->assertSame(0, $this->transport->requestCount());
        $this->assertSame(IntegrationStatus::CONNECTED, $integration->fresh()->status);
    }

    public function test_it_sweeps_every_account_rather_than_only_the_first(): void
    {
        $first = Integration::factory()->google()->expiringSoon()->create();
        $second = Integration::factory()->google()->expiringSoon()->create();
        $this->transport->queue(
            FakeTransport::json(self::REVOKED_GRANT_BODY, Response::HTTP_BAD_REQUEST),
            FakeTransport::json(self::REVOKED_GRANT_BODY, Response::HTTP_BAD_REQUEST),
        );

        CheckIntegrationHealthJob::dispatch();

        $this->assertSame(IntegrationStatus::EXPIRED, $first->fresh()->status);
        $this->assertSame(IntegrationStatus::EXPIRED, $second->fresh()->status);
    }
}
