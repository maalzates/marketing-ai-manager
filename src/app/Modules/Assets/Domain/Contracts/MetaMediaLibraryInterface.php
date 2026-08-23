<?php

declare(strict_types=1);

namespace App\Modules\Assets\Domain\Contracts;

/**
 * The port Campaigns implements with its own Meta client, bound in CampaignsServiceProvider.
 * Assets deliberately does not bind it: the Meta client belongs to Campaigns, and binding it
 * here would point this module at one that depends on it.
 *
 * Both methods receive a signed URL of this application's own `GET /media/{token}` route, never
 * bytes, because `core.md` §13 forbids Assets from staging a file. What the implementation does
 * with that URL differs per endpoint, and the difference is the implementation's problem:
 *
 * - `/act_{id}/advideos` accepts a `file_url` parameter, so the video path forwards the URL.
 * - `/act_{id}/adimages` accepts bytes only, so the image path must GET the URL and stream the
 *   response body into its multipart request. Passing the URL through to Meta will fail.
 *
 * The `act_{id}` is resolved by the implementation from its own settings; the `$accountId` here
 * is this application's tenant id, not a Meta ad account.
 */
interface MetaMediaLibraryInterface
{
    /**
     * @param  string  $fetchUrl  GET this and post the bytes; Meta will not fetch it for you
     * @return string the `hash` Meta returns for an ad image
     */
    public function uploadImage(int $accountId, string $filename, string $fetchUrl): string;

    /**
     * @param  string  $fileUrl  forwarded to Meta as `file_url`; Meta fetches it itself
     * @return string the `id` Meta returns for an ad video
     */
    public function uploadVideo(int $accountId, string $name, string $fileUrl): string;
}
