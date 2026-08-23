<?php

declare(strict_types=1);

namespace App\Modules\Content\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ApiCallFailedException;
use App\Modules\Core\Domain\Exceptions\ClientException;
use App\Modules\Core\Domain\Support\SecretMasker;
use Symfony\Component\HttpFoundation\Response;

class YoutubeApiException extends ClientException
{
    public static function fromApiCall(ApiCallFailedException $exception, string $operation): self
    {
        $error = $exception->getContext()['response_body']['error'] ?? [];

        $failure = new self(
            $error['message'] ?? 'YouTube rejected the request.',
            Response::HTTP_BAD_GATEWAY,
            $exception,
        );

        $failure->context = (new SecretMasker)->mask([
            'operation' => $operation,
            'reason' => $error['errors'][0]['reason'] ?? null,
            ...$exception->getContext(),
        ]);

        return $failure;
    }
}
