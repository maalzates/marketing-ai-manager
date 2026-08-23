<?php

declare(strict_types=1);

namespace App\Modules\Core\Presentation\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

/**
 * Every JSON body this API returns has the same two top-level keys, so the frontend
 * never has to branch on response shape.
 */
class ApiResponse
{
    public function success(mixed $data = '', int $statusCode = HttpStatus::HTTP_OK): JsonResponse
    {
        return Response::json(['result' => $data, 'errors' => []], $statusCode);
    }

    public function created(mixed $data = ''): JsonResponse
    {
        return $this->success($data, HttpStatus::HTTP_CREATED);
    }

    public function accepted(mixed $data = ''): JsonResponse
    {
        return $this->success($data, HttpStatus::HTTP_ACCEPTED);
    }

    public function error(
        string $message = '',
        int $statusCode = HttpStatus::HTTP_INTERNAL_SERVER_ERROR,
        array $extra = [],
    ): JsonResponse {
        return Response::json(
            [
                'result' => [],
                'errors' => array_merge(['message' => $message, 'status_code' => $statusCode], $extra),
            ],
            $statusCode
        );
    }

    public function noContent(): HttpResponse
    {
        return Response::noContent();
    }
}
