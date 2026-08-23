<?php

declare(strict_types=1);

namespace App\Modules\Assets\Presentation\Http\Controllers\Web;

use App\Modules\Assets\Application\Services\AssetStreamService;
use App\Modules\Assets\Infrastructure\Persistence\Asset;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Public on purpose and outside `/api`: Meta's fetcher carries no bearer token. The token in the
 * path is the whole authorisation, and the bytes are piped from Drive to the socket — this
 * application never holds the file.
 */
class MediaStreamController
{
    public function __construct(private readonly AssetStreamService $service) {}

    public function __invoke(string $token): StreamedResponse
    {
        return $this->streamOf($this->service->resolve($token));
    }

    private function streamOf(Asset $asset): StreamedResponse
    {
        return new StreamedResponse(
            fn () => $this->service->pipe($asset, fopen('php://output', 'wb')),
            Response::HTTP_OK,
            [
                'Content-Type' => $asset->mime_type ?? 'application/octet-stream',
                'Content-Disposition' => 'inline',
                'Cache-Control' => 'private, no-store',
            ],
        );
    }
}
