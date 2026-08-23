<?php

declare(strict_types=1);

namespace App\Modules\Proposals\Presentation\Http\Controllers\Api;

use App\Modules\Core\Presentation\Http\Controllers\Api\ApiController;
use App\Modules\Proposals\Application\Services\ProposalExecutionService;
use App\Modules\Proposals\Presentation\Http\Requests\ProposalScopeRequest;
use Illuminate\Http\JsonResponse;

/**
 * The human approval door, and the only class the container will hand a
 * ProposalExecutionService to. It exists on its own so that permission is a single,
 * greppable contextual binding rather than a convention.
 */
class AcceptProposalController extends ApiController
{
    public function __construct(private readonly ProposalExecutionService $service)
    {
        parent::__construct();
    }

    public function __invoke(ProposalScopeRequest $request): JsonResponse
    {
        return $this->response->success($this->service->accept(
            $request->proposalId(),
            $request->accountId(),
            $request->userId(),
        ));
    }
}
