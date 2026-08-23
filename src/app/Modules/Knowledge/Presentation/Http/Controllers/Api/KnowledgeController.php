<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Presentation\Http\Controllers\Api;

use App\Modules\Core\Presentation\Http\Controllers\Api\ApiController;
use App\Modules\Knowledge\Application\Services\KnowledgeService;
use App\Modules\Knowledge\Presentation\Http\Requests\IndexKnowledgeRequest;
use App\Modules\Knowledge\Presentation\Http\Requests\ShowKnowledgeRequest;
use Illuminate\Http\JsonResponse;

class KnowledgeController extends ApiController
{
    public function __construct(private readonly KnowledgeService $service)
    {
        parent::__construct();
    }

    public function index(IndexKnowledgeRequest $request): JsonResponse
    {
        return $this->response->success($this->service->listByType($request->type(), $request->locale()));
    }

    public function show(ShowKnowledgeRequest $request): JsonResponse
    {
        return $this->response->success(
            $this->service->latest($request->type(), $request->key(), $request->locale()),
        );
    }
}
