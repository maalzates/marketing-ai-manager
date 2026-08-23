<?php

declare(strict_types=1);

namespace App\Modules\Core\Presentation\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

class HealthController extends ApiController
{
    public function __invoke(): JsonResponse
    {
        return $this->response->success(['status' => 'ok']);
    }
}
