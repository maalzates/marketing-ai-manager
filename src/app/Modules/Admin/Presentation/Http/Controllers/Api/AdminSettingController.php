<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presentation\Http\Controllers\Api;

use App\Modules\Admin\Application\Services\AdminSettingsService;
use App\Modules\Admin\Presentation\Http\Requests\IndexGlobalSettingsRequest;
use App\Modules\Admin\Presentation\Http\Requests\UpdateGlobalSettingsRequest;
use App\Modules\Core\Presentation\Http\Controllers\Api\ApiController;
use Illuminate\Http\JsonResponse;

class AdminSettingController extends ApiController
{
    public function __construct(private readonly AdminSettingsService $service)
    {
        parent::__construct();
    }

    public function index(IndexGlobalSettingsRequest $request): JsonResponse
    {
        return $this->response->success($this->service->all($request->accountId()));
    }

    public function update(UpdateGlobalSettingsRequest $request): JsonResponse
    {
        return $this->response->success($this->service->update($request->toDTO()));
    }
}
