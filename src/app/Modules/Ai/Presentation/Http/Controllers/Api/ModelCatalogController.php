<?php

declare(strict_types=1);

namespace App\Modules\Ai\Presentation\Http\Controllers\Api;

use App\Modules\Ai\Application\Services\ModelCatalogRefresher;
use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Presentation\Http\Controllers\Api\ApiController;
use Illuminate\Http\JsonResponse;

class ModelCatalogController extends ApiController
{
    public function __construct(private readonly ModelCatalogRefresher $refresher)
    {
        parent::__construct();
    }

    /**
     * Runs inline rather than queued: the caller pressed a button and is waiting to see the
     * new list. It is three read-only calls on the account's own keys.
     */
    public function store(AccountContext $account): JsonResponse
    {
        return $this->response->success($this->refresher->refresh($account->accountId));
    }
}
