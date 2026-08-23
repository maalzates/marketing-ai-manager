<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Presentation\Http\Controllers\Api;

use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Presentation\Http\Controllers\Api\ApiController;
use App\Modules\Reporting\Application\Jobs\RunGuardianJob;
use App\Modules\Strategies\Application\Services\StrategyService;
use Illuminate\Http\JsonResponse;

/**
 * The manual trigger. It ignores `guardian.enabled` on purpose: that switch pauses the
 * automation, and a user pressing the button is asking for this run specifically.
 */
class GuardianRunController extends ApiController
{
    public function __construct(
        private readonly StrategyService $strategies,
        private readonly AccountContext $context,
    ) {
        parent::__construct();
    }

    public function __invoke(string $strategy): JsonResponse
    {
        RunGuardianJob::dispatch(
            $this->context->accountId,
            (int) $this->strategies->find((int) $strategy, $this->context->accountId)->id,
        );

        return $this->response->accepted(['strategy_id' => (int) $strategy, 'status' => 'queued']);
    }
}
