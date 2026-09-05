<?php

declare(strict_types=1);

namespace App\Modules\Accounts\Presentation\Http\Controllers\Api;

use App\Modules\Accounts\Application\Services\AccountService;
use App\Modules\Accounts\Presentation\Http\Requests\UpdateAccountRequest;
use App\Modules\Core\Presentation\Http\Controllers\Api\ApiController;
use Illuminate\Http\JsonResponse;

class AccountController extends ApiController
{
    public function __construct(private readonly AccountService $service)
    {
        parent::__construct();
    }

    public function update(UpdateAccountRequest $request): JsonResponse
    {
        return $this->response->success($this->service->update($request->toDTO()));
    }
}
