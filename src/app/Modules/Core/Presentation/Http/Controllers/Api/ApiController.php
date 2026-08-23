<?php

declare(strict_types=1);

namespace App\Modules\Core\Presentation\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Core\Presentation\Http\Responses\ApiResponse;

abstract class ApiController extends Controller
{
    public function __construct(protected readonly ApiResponse $response = new ApiResponse) {}
}
