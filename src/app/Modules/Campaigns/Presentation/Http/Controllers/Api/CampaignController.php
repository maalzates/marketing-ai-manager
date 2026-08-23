<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Presentation\Http\Controllers\Api;

use App\Modules\Campaigns\Application\Jobs\SyncCampaignMetricsJob;
use App\Modules\Campaigns\Application\Services\CampaignService;
use App\Modules\Campaigns\Presentation\Http\Requests\PauseCampaignRequest;
use App\Modules\Campaigns\Presentation\Http\Requests\SyncCampaignMetricsRequest;
use App\Modules\Campaigns\Presentation\Http\Resources\CampaignResource;
use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Presentation\Http\Controllers\Api\ApiController;
use Illuminate\Http\JsonResponse;

/**
 * There is no create endpoint: a campaign is born from an accepted proposal, never from a
 * POST. Read, sync and pause are the only doors, and each of them delegates every rule.
 */
class CampaignController extends ApiController
{
    public function __construct(
        private readonly CampaignService $service,
        private readonly AccountContext $context,
    ) {
        parent::__construct();
    }

    public function show(string $experiment): JsonResponse
    {
        return $this->response->success(new CampaignResource(
            $this->service->forExperiment($this->context->accountId, (int) $experiment),
        ));
    }

    /** Insights are a paid, rate-limited call: the request queues the pull and returns. */
    public function sync(SyncCampaignMetricsRequest $request): JsonResponse
    {
        SyncCampaignMetricsJob::dispatch($request->toDTO());

        return $this->response->accepted();
    }

    public function pause(PauseCampaignRequest $request): JsonResponse
    {
        return $this->response->success(new CampaignResource($this->service->pause($request->toDTO())));
    }
}
