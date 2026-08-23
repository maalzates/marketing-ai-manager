<?php

declare(strict_types=1);

namespace App\Modules\Proposals\Presentation\Http\Controllers\Api;

use App\Modules\Core\Presentation\Http\Controllers\Api\ApiController;
use App\Modules\Proposals\Application\Services\ProposalService;
use App\Modules\Proposals\Presentation\Http\Requests\IndexProposalRequest;
use App\Modules\Proposals\Presentation\Http\Requests\ProposalScopeRequest;
use App\Modules\Proposals\Presentation\Http\Requests\RejectProposalRequest;
use Illuminate\Http\JsonResponse;

class ProposalController extends ApiController
{
    public function __construct(private readonly ProposalService $service)
    {
        parent::__construct();
    }

    public function index(IndexProposalRequest $request): JsonResponse
    {
        return $this->response->success($this->service->list($request->toDTO()));
    }

    public function show(ProposalScopeRequest $request): JsonResponse
    {
        return $this->response->success($this->service->find($request->proposalId(), $request->accountId()));
    }

    public function reject(RejectProposalRequest $request): JsonResponse
    {
        return $this->response->success($this->service->reject(
            $request->proposalId(),
            $request->accountId(),
            $request->userId(),
            $request->reason(),
        ));
    }
}
