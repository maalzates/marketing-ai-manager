<?php

declare(strict_types=1);

namespace App\Modules\Ai\Presentation\Http\Controllers\Api;

use App\Modules\Ai\Application\Services\FieldSuggestionService;
use App\Modules\Ai\Presentation\Http\Requests\SuggestFieldRequest;
use App\Modules\Core\Presentation\Http\Controllers\Api\ApiController;
use Illuminate\Http\JsonResponse;

class AiSuggestionController extends ApiController
{
    public function __construct(private readonly FieldSuggestionService $service)
    {
        parent::__construct();
    }

    public function store(SuggestFieldRequest $request): JsonResponse
    {
        return $this->response->success($this->service->suggest($request->toDTO()));
    }
}
