<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Presentation\Http\Controllers\Api;

use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Presentation\Http\Controllers\Api\ApiController;
use App\Modules\Onboarding\Application\Services\OnboardingService;
use App\Modules\Onboarding\Presentation\Http\Requests\OnboardingStepRequest;
use Illuminate\Http\JsonResponse;

class OnboardingController extends ApiController
{
    public function __construct(
        private readonly OnboardingService $service,
        private readonly AccountContext $account,
    ) {
        parent::__construct();
    }

    public function show(): JsonResponse
    {
        return $this->response->success($this->service->state($this->account->accountId));
    }

    /** Answers with the whole wizard state so the frontend lands on the next step in one round trip. */
    public function complete(OnboardingStepRequest $request): JsonResponse
    {
        $this->service->completeStep($request->toDTO());

        return $this->response->success($this->service->state($this->account->accountId));
    }

    public function skip(OnboardingStepRequest $request): JsonResponse
    {
        $this->service->skipStep($request->toDTO());

        return $this->response->success($this->service->state($this->account->accountId));
    }

    public function checklist(): JsonResponse
    {
        return $this->response->success($this->service->checklist($this->account->accountId));
    }
}
