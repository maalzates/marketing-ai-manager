<?php

declare(strict_types=1);

namespace Tests\Feature\Competitors;

use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Competitors\Application\Jobs\SyncCompetitorJob;
use App\Modules\Competitors\Domain\Exceptions\ApifyCreditExhaustedException;
use App\Modules\Competitors\Domain\Exceptions\ApifyRequestFailedException;
use App\Modules\Competitors\Infrastructure\Persistence\Competitor;
use App\Modules\Integrations\Infrastructure\Persistence\Integration;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\FakeTransport;
use Tests\TestCase;

/**
 * The scraping half of the pipeline, replaying the bodies Apify's own documentation prints.
 *
 * Two things here are worth more than the happy path: the upsert key, because a competitor
 * synced twice a day for a month would otherwise hold thirty copies of every post; and the
 * error branch, because Apify answers `actor-not-found` with a 400 and `insufficient-credit`
 * with a 402 — a client that read the status instead of `error.type` would tell the user the
 * wrong thing about their money.
 */
class SyncCompetitorJobTest extends TestCase
{
    use RefreshDatabase;

    private const string APIFY_TOKEN = 'apify_api_Yn4Rq8Vt1Xy6Kd0Lp3Mw7Sc2Hb5Ge9Ja';

    private const string RUN_BODY = '{"data":{"id":"HG7ML7M8z78YcAPEB","actId":"shu8hvrXbJbY3Eb9W","status":"SUCCEEDED",'
        .'"defaultDatasetId":"wmKPijuyDnPZAPRMk","defaultKeyValueStoreId":"eJNzqsbPiopwJcgGQ",'
        .'"startedAt":"2026-08-23T15:54:12.101Z","finishedAt":"2026-08-23T15:59:55.902Z","usageTotalUsd":0.2654}}';

    private const string RUNNING_RUN_BODY = '{"data":{"id":"HG7ML7M8z78YcAPEB","actId":"shu8hvrXbJbY3Eb9W",'
        .'"status":"RUNNING","defaultDatasetId":"wmKPijuyDnPZAPRMk"}}';

    private const string ACTOR_NOT_FOUND_BODY = '{"error":{"type":"actor-not-found",'
        .'"message":"Actor with ID or name \'apify~no-such-actor\' was not found."}}';

    private const string INSUFFICIENT_CREDIT_BODY = '{"error":{"type":"insufficient-credit",'
        .'"message":"You are out of usage credit."}}';

    private Account $account;

    private Competitor $competitor;

    private FakeTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-08-23 10:00:00'));

        $this->transport = FakeTransport::silent()->install($this->app);
        $this->account = Account::factory()->create();
        $this->competitor = Competitor::factory()->create([
            'account_id' => $this->account->id,
            'handle' => 'natgeo',
        ]);

        Integration::factory()->apify()->create([
            'account_id' => $this->account->id,
            'credentials' => ['api_key' => self::APIFY_TOKEN],
        ]);
    }

    public function test_the_posts_a_run_returned_are_persisted(): void
    {
        $this->replayASuccessfulRun();

        $this->sync();

        $this->assertDatabaseHas('competitor_posts', [
            'competitor_id' => $this->competitor->id,
            'external_id' => '3923124318436838545',
            'type' => 'image',
            'likes' => 55952,
            'comments_count' => 110,
        ]);
    }

    public function test_a_reel_is_recognised_by_its_product_type_rather_than_its_media_type(): void
    {
        $this->replayASuccessfulRun();

        $this->sync();

        $this->assertDatabaseHas('competitor_posts', [
            'external_id' => '3918440021553311902',
            'type' => 'reel',
        ]);
    }

    public function test_syncing_twice_upserts_by_competitor_and_external_id_instead_of_duplicating(): void
    {
        $this->replayASuccessfulRun();
        $this->sync();

        $this->replayASuccessfulRun();
        $this->sync();

        $this->assertDatabaseCount('competitor_posts', 2);
    }

    public function test_a_second_sync_refreshes_the_metrics_of_a_post_it_already_had(): void
    {
        $this->replayASuccessfulRun();
        $this->sync();

        $this->transport->queue(
            FakeTransport::json(self::RUN_BODY),
            FakeTransport::json($this->datasetItems(likesOfTheFirstPost: 60000)),
        );
        $this->sync();

        $this->assertDatabaseHas('competitor_posts', [
            'external_id' => '3923124318436838545',
            'likes' => 60000,
        ]);
    }

    public function test_the_sync_stamps_the_moment_the_competitor_was_last_read(): void
    {
        $this->replayASuccessfulRun();

        $this->sync();

        $this->assertSame(
            '2026-08-23 10:00:00',
            (string) $this->competitor->refresh()->last_synced_at?->toDateTimeString(),
        );
    }

    public function test_the_cost_apify_reported_for_the_run_is_written_to_the_usage_ledger(): void
    {
        $this->replayASuccessfulRun();

        $this->sync();

        $this->assertDatabaseHas('apify_usage_logs', [
            'account_id' => $this->account->id,
            'actor_id' => 'apify~instagram-scraper',
            'run_id' => 'HG7ML7M8z78YcAPEB',
            'results_count' => 2,
            'estimated_cost_usd' => '0.265400',
        ]);
    }

    public function test_hidden_likes_are_stored_as_unknown_rather_than_as_zero(): void
    {
        $this->transport->queue(
            FakeTransport::json(self::RUN_BODY),
            FakeTransport::json($this->datasetItems(likesOfTheFirstPost: -1)),
        );

        $this->sync();

        $this->assertNull(
            $this->competitor->posts()->where('external_id', '3923124318436838545')->sole()->likes,
        );
    }

    public function test_a_run_that_is_still_going_is_long_polled_until_it_finishes(): void
    {
        $this->transport->queue(
            FakeTransport::json(self::RUNNING_RUN_BODY),
            FakeTransport::json(self::RUN_BODY),
            FakeTransport::json($this->datasetItems()),
        );

        $this->sync();

        $this->assertSame('/v2/actor-runs/HG7ML7M8z78YcAPEB', $this->transport->path(1));
        $this->assertStringContainsString('waitForFinish=60', $this->transport->query(1));
    }

    /**
     * `actor-not-found` is documented under 400 and `insufficient-credit` under 402. Sent with
     * the same status, they still have to produce different exceptions — which is only possible
     * if the branch reads `error.type`.
     */
    public function test_a_credit_error_is_recognised_by_its_type_even_when_the_status_is_a_400(): void
    {
        $this->transport->queue(FakeTransport::json(self::INSUFFICIENT_CREDIT_BODY, Response::HTTP_BAD_REQUEST));

        $this->expectException(ApifyCreditExhaustedException::class);

        $this->sync();
    }

    public function test_an_unknown_actor_arrives_as_a_400_and_is_not_mistaken_for_a_credit_problem(): void
    {
        $this->transport->queue(FakeTransport::json(self::ACTOR_NOT_FOUND_BODY, Response::HTTP_BAD_REQUEST));

        try {
            $this->sync();
            $this->fail('The unknown actor did not raise a domain exception.');
        } catch (ApifyRequestFailedException $exception) {
            $this->assertSame('actor-not-found', $exception->getContext()['error_type']);
            $this->assertSame(Response::HTTP_BAD_REQUEST, $exception->getContext()['http_status_code']);
        }
    }

    public function test_the_apify_token_reaches_the_provider_and_nothing_else(): void
    {
        $this->transport->queue(FakeTransport::json(self::ACTOR_NOT_FOUND_BODY, Response::HTTP_BAD_REQUEST));

        try {
            $this->sync();
            $this->fail('The unknown actor did not raise a domain exception.');
        } catch (ApifyRequestFailedException $exception) {
            $this->assertSame('Bearer '.self::APIFY_TOKEN, $this->transport->header('Authorization'));
            $this->assertStringNotContainsString(self::APIFY_TOKEN, $exception->getMessage());
            $this->assertStringNotContainsString(
                self::APIFY_TOKEN,
                (string) json_encode($exception->getContext()),
            );
        }
    }

    public function test_the_run_is_capped_so_a_runaway_actor_cannot_spend_the_accounts_credit(): void
    {
        $this->replayASuccessfulRun();

        $this->sync();

        $this->assertStringContainsString('maxItems=50', $this->transport->query());
        $this->assertStringContainsString('maxTotalChargeUsd=2', $this->transport->query());
    }

    private function sync(): void
    {
        SyncCompetitorJob::dispatch((int) $this->account->id, (int) $this->competitor->id);
    }

    private function replayASuccessfulRun(): void
    {
        $this->transport->queue(
            FakeTransport::json(self::RUN_BODY),
            FakeTransport::json($this->datasetItems()),
        );
    }

    private function datasetItems(int $likesOfTheFirstPost = 55952): string
    {
        return (string) json_encode([
            [
                'id' => '3923124318436838545',
                'type' => 'Image',
                'shortCode' => 'DZxvMgyH8yR',
                'caption' => 'Photograph by @carstenpeter | Deep inside the cave system.',
                'url' => 'https://www.instagram.com/p/DZxvMgyH8yR/',
                'commentsCount' => 110,
                'likesCount' => $likesOfTheFirstPost,
                'videoViewCount' => null,
                'videoPlayCount' => null,
                'timestamp' => '2026-06-20T10:00:04.000Z',
                'ownerUsername' => 'natgeo',
                'productType' => null,
            ],
            [
                'id' => '3918440021553311902',
                'type' => 'Video',
                'shortCode' => 'DZhAxKLpQ1m',
                'caption' => 'Behind the scenes of our latest expedition',
                'url' => 'https://www.instagram.com/reel/DZhAxKLpQ1m/',
                'commentsCount' => 342,
                'likesCount' => 128744,
                'videoViewCount' => 2104338,
                'videoPlayCount' => 2988120,
                'timestamp' => '2026-06-14T18:30:11.000Z',
                'ownerUsername' => 'natgeo',
                'productType' => 'clips',
            ],
        ]);
    }
}
