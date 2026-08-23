<?php

declare(strict_types=1);

namespace App\Modules\Settings\Presentation\Http\Controllers\Api;

use App\Modules\Core\Presentation\Http\Controllers\Api\ApiController;
use App\Modules\Settings\Application\Services\SettingsService;
use App\Modules\Settings\Presentation\Http\Requests\IndexSettingsRequest;
use App\Modules\Settings\Presentation\Http\Requests\UpdateSettingsRequest;
use Illuminate\Http\JsonResponse;

class SettingController extends ApiController
{
    public function __construct(private readonly SettingsService $service)
    {
        parent::__construct();
    }

    public function index(IndexSettingsRequest $request): JsonResponse
    {
        return $this->response->success($this->service->effective($request->toDTO()));
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        return $this->response->success($this->service->update($request->toDTO()));
    }
}
