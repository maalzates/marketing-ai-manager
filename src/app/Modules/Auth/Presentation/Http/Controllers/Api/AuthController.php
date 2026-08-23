<?php

declare(strict_types=1);

namespace App\Modules\Auth\Presentation\Http\Controllers\Api;

use App\Modules\Auth\Application\Services\AuthService;
use App\Modules\Auth\Presentation\Http\Requests\GoogleCallbackRequest;
use App\Modules\Core\Presentation\Http\Controllers\Api\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthController extends ApiController
{
    public function __construct(private readonly AuthService $service)
    {
        parent::__construct();
    }

    /**
     * Public. Returns the consent URL instead of a 302 because the SPA and a future native
     * client both need to open it themselves; a redirect would tie sign-in to a browser.
     */
    public function redirect(): JsonResponse
    {
        return $this->response->success($this->service->authorisationUrl());
    }

    /** Public: Google calls it. The single-use `state` is what proves we issued the request. */
    public function callback(GoogleCallbackRequest $request): JsonResponse
    {
        return $this->response->success($this->service->handleCallback($request->code(), $request->state()));
    }

    public function me(Request $request): JsonResponse
    {
        return $this->response->success($this->service->me($request->user()));
    }

    public function logout(Request $request): Response
    {
        $this->service->logout($request->user());

        return $this->response->noContent();
    }
}
