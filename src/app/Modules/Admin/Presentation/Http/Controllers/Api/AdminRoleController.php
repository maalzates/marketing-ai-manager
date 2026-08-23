<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presentation\Http\Controllers\Api;

use App\Modules\Accounts\Application\Services\RoleService;
use App\Modules\Admin\Presentation\Http\Requests\StoreRoleRequest;
use App\Modules\Admin\Presentation\Http\Requests\UpdateRoleRequest;
use App\Modules\Core\Presentation\Http\Controllers\Api\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * The role CRUD lives in the admin panel (core.md §7) while roles themselves belong to the
 * Accounts module, so this controller is a door onto RoleService and owns no logic.
 */
class AdminRoleController extends ApiController
{
    public function __construct(private readonly RoleService $service)
    {
        parent::__construct();
    }

    public function index(): JsonResponse
    {
        return $this->response->success($this->service->findAll());
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        return $this->response->created($this->service->create($request->toDTO()));
    }

    public function update(UpdateRoleRequest $request): JsonResponse
    {
        return $this->response->success($this->service->update($request->toDTO()));
    }

    public function destroy(string $id): Response
    {
        $this->service->delete((int) $id);

        return $this->response->noContent();
    }
}
