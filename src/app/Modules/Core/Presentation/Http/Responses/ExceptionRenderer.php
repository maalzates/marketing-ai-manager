<?php

declare(strict_types=1);

namespace App\Modules\Core\Presentation\Http\Responses;

use App\Modules\Core\Domain\Exceptions\ApiException;
use App\Modules\Core\Domain\Exceptions\ClientException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Turns any exception into the API's error envelope, so a 404 from the router and a
 * domain exception from a service look identical to the frontend.
 */
readonly class ExceptionRenderer
{
    private const string GENERIC_MESSAGE = 'Something went wrong. Please try again later.';

    public function __construct(private ApiResponse $response = new ApiResponse) {}

    public function render(Throwable $exception, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*') && ! $request->expectsJson()) {
            return null;
        }

        return match (true) {
            $exception instanceof ClientException => $this->response->error(
                $exception->getClientMessage(),
                $exception->getHttpStatusCode(),
                $exception->getExtras()
            ),
            $exception instanceof ApiException => $this->response->error(
                $exception->getMessage(),
                $exception->getHttpStatusCode()
            ),
            $exception instanceof ValidationException => $this->response->error(
                $exception->getMessage(),
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ['fields' => $exception->errors()]
            ),
            $exception instanceof AuthenticationException => $this->response->error(
                'Unauthenticated.',
                Response::HTTP_UNAUTHORIZED
            ),
            $exception instanceof AuthorizationException => $this->response->error(
                'This action is unauthorized.',
                Response::HTTP_FORBIDDEN
            ),
            $exception instanceof ModelNotFoundException => $this->response->error(
                'Resource not found.',
                Response::HTTP_NOT_FOUND
            ),
            $exception instanceof HttpExceptionInterface => $this->response->error(
                $exception->getMessage() ?: Response::$statusTexts[$exception->getStatusCode()] ?? self::GENERIC_MESSAGE,
                $exception->getStatusCode()
            ),
            // An unexpected exception's message is whatever a library decided to write;
            // it only reaches the caller with debug on.
            default => $this->response->error(
                config('app.debug') ? $exception->getMessage() : self::GENERIC_MESSAGE,
                Response::HTTP_INTERNAL_SERVER_ERROR
            ),
        };
    }
}
