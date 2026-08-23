<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presentation\Http\Controllers\Api;

use App\Modules\Admin\Application\Services\AdminUserService;
use App\Modules\Admin\Presentation\Http\Requests\IndexAdminUserRequest;
use App\Modules\Admin\Presentation\Http\Requests\ShowAdminUserRequest;
use App\Modules\Admin\Presentation\Http\Requests\StoreAdminUserRequest;
use App\Modules\Admin\Presentation\Http\Requests\UpdateAdminUserRequest;
use App\Modules\Admin\Presentation\Http\Requests\UserRoleRequest;
use App\Modules\Core\Presentation\Http\Controllers\Api\ApiController;
use Illuminate\Http\JsonResponse;

class AdminUserController extends ApiController
{
    public function __construct(private readonly AdminUserService $service)
    {
        parent::__construct();
    }

    public function index(IndexAdminUserRequest $request): JsonResponse
    {
        return $this->response->success($this->service->findAll($request->toDTO()));
    }

    public function show(ShowAdminUserRequest $request): JsonResponse
    {
        return $this->response->success($this->service->detail($request->toDTO()));
    }

    public function store(StoreAdminUserRequest $request): JsonResponse
    {
        return $this->response->created($this->service->create($request->toDTO()));
    }

    public function update(UpdateAdminUserRequest $request): JsonResponse
    {
        return $this->response->success($this->service->update($request->toDTO()));
    }

    public function assignRole(UserRoleRequest $request): JsonResponse
    {
        return $this->response->success($this->service->assignRole($request->toDTO()));
    }

    public function removeRole(UserRoleRequest $request): JsonResponse
    {
        return $this->response->success($this->service->removeRole($request->toDTO()));
    }
}
