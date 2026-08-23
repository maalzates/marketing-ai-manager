<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presentation\Http\Resources;

use App\Modules\Admin\Infrastructure\Persistence\ApplicationApiKey;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ApplicationApiKey
 *
 * The listing view of a key. `prefix` is the only part of the token that appears here,
 * which is exactly enough to recognise a key and useless for calling the API with it.
 */
class ApplicationApiKeyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'account_id' => $this->account_id,
            'account_name' => $this->account?->name,
            'name' => $this->name,
            'prefix' => $this->prefix,
            'abilities' => $this->abilities,
            'last_used_at' => $this->last_used_at?->toIso8601String(),
            'revoked_at' => $this->revoked_at?->toIso8601String(),
            'created_by' => $this->creator?->email,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
