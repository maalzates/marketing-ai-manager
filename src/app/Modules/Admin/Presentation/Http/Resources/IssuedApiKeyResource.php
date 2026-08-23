<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presentation\Http\Resources;

use App\Modules\Admin\Domain\Support\IssuedApiKey;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin IssuedApiKey
 *
 * The only response in the whole application that contains a token in clear. It is
 * reachable from exactly one place — POST /api/v1/admin/api-keys — and the value it
 * carries cannot be produced again by any other endpoint, query or log.
 */
class IssuedApiKeyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'token' => $this->plainToken,
            'key' => new ApplicationApiKeyResource($this->key),
            'shown_once' => true,
        ];
    }
}
