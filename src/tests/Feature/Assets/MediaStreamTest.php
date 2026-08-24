<?php

declare(strict_types=1);

namespace Tests\Feature\Assets;

use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Assets\Application\Services\AssetStreamService;
use App\Modules\Assets\Infrastructure\Persistence\Asset;
use App\Modules\Integrations\Infrastructure\Persistence\Integration;
use Carbon\CarbonImmutable;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\Support\FakeTransport;
use Tests\TestCase;

/**
 * The only public, unauthenticated door in the build, and it hands out a user's private
 * Drive bytes. The token is the whole authorisation, so every way of arriving without a
 * good one has to end in the same 404 — and the mint itself has to keep working, because
 * a URL that cannot be built kills every Instagram publish and every creative upload.
 */
class MediaStreamTest extends TestCase
{
    use RefreshDatabase;

    private const string DRIVE_BYTES = "\x00\x00\x00\x18ftypmp42fake-reel-bytes";

    private Account $account;

    private Asset $asset;

    private FakeTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-09-01 08:00:00'));

        $this->transport = FakeTransport::silent()->install($this->app);
        $this->account = Account::factory()->create();
        Integration::factory()->google()->for($this->account)->create();
        $this->asset = Asset::factory()->ready()->create([
            'account_id' => $this->account->id,
            'drive_file_id' => 'drive-file-abc123',
            'mime_type' => 'video/mp4',
        ]);
    }

    /**
     * The regression test for a shipped defect: the service's route name and the name the
     * route was registered under drifted apart, so route() threw and every publish died.
     */
    public function test_mints_a_url_that_resolves_to_the_registered_media_route(): void
    {
        $url = $this->service()->signedUrlFor($this->asset);

        $this->assertStringStartsWith(url('/media/'), $url);
        $this->assertSame(
            AssetStreamService::ROUTE_NAME,
            Route::getRoutes()->match(Request::create($url))->getName(),
        );
    }

    public function test_streams_the_drive_bytes_for_a_valid_token(): void
    {
        $this->transport->queue(new Response(200, [], self::DRIVE_BYTES));

        $response = $this->get($this->urlFor($this->asset));

        $response->assertOk()->assertHeader('Content-Type', 'video/mp4');
        $this->assertSame(self::DRIVE_BYTES, $response->streamedContent());
    }

    public function test_asks_drive_for_the_raw_media_of_that_one_file(): void
    {
        $this->transport->queue(new Response(200, [], self::DRIVE_BYTES));

        $this->get($this->urlFor($this->asset))->streamedContent();

        $this->assertStringEndsWith('/files/drive-file-abc123', $this->transport->path());
        $this->assertSame('alt=media', $this->transport->query());
    }

    public function test_pipes_the_bytes_without_leaving_a_file_on_disk(): void
    {
        $this->transport->queue(new Response(200, [], self::DRIVE_BYTES));
        $before = $this->storageContents();

        $response = $this->get($this->urlFor($this->asset));

        $this->assertInstanceOf(StreamedResponse::class, $response->baseResponse);
        $response->streamedContent();
        $this->assertSame($before, $this->storageContents());
    }

    public function test_rejects_a_forged_token(): void
    {
        $this->get('/media/'.$this->urlSafe('{"asset":1,"account":1,"expires":99999999999}').'.'.$this->urlSafe('not-a-signature'))
            ->assertNotFound();

        $this->assertSame(0, $this->transport->requestCount());
    }

    public function test_rejects_a_token_whose_payload_was_edited_after_signing(): void
    {
        $token = $this->tokenFor($this->asset);
        [$payload, $signature] = explode('.', $token, 2);

        $this->get('/media/'.$this->urlSafe(strrev(base64_decode(strtr($payload, '-_', '+/'), true))).'.'.$signature)
            ->assertNotFound();
    }

    public function test_rejects_an_expired_token(): void
    {
        $token = $this->tokenFor($this->asset);

        $this->travelTo(CarbonImmutable::parse('2026-09-02 09:00:00'));

        $this->get('/media/'.$token)->assertNotFound();
        $this->assertSame(0, $this->transport->requestCount());
    }

    public function test_accepts_a_token_one_hour_before_it_expires(): void
    {
        $token = $this->tokenFor($this->asset);
        $this->transport->queue(new Response(200, [], self::DRIVE_BYTES));

        $this->travelTo(CarbonImmutable::parse('2026-09-02 07:00:00'));

        $this->get('/media/'.$token)->assertOk();
    }

    /** The account is inside the signed claims, so a token stops working the moment it would cross accounts. */
    public function test_a_token_stops_resolving_once_its_asset_belongs_to_another_account(): void
    {
        $token = $this->tokenFor($this->asset);

        $this->asset->update(['account_id' => Account::factory()->create()->id]);

        $this->get('/media/'.$token)->assertNotFound();
        $this->assertSame(0, $this->transport->requestCount());
    }

    public function test_one_accounts_token_does_not_reach_another_accounts_asset(): void
    {
        $foreign = Asset::factory()->ready()->create(['account_id' => Account::factory()->create()->id]);
        $token = $this->tokenFor($foreign);

        [$payload, $signature] = explode('.', $token, 2);
        $claims = json_decode(base64_decode(strtr($payload, '-_', '+/'), true), true);
        $claims['account'] = $this->account->id;

        $this->get('/media/'.$this->urlSafe((string) json_encode($claims)).'.'.$signature)->assertNotFound();
    }

    private function service(): AssetStreamService
    {
        return $this->app->make(AssetStreamService::class);
    }

    private function urlFor(Asset $asset): string
    {
        return $this->service()->signedUrlFor($asset);
    }

    private function tokenFor(Asset $asset): string
    {
        return (string) preg_replace('#^.*/media/#', '', $this->urlFor($asset));
    }

    private function urlSafe(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /** @return list<string> */
    private function storageContents(): array
    {
        $files = [];

        foreach (['app', 'framework/cache'] as $directory) {
            $files = [...$files, ...glob(storage_path($directory).'/*') ?: []];
        }

        sort($files);

        return $files;
    }
}
