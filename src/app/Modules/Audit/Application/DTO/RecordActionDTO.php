<?php

declare(strict_types=1);

namespace App\Modules\Audit\Application\DTO;

use App\Modules\Audit\Domain\Enums\ActionOrigin;

readonly class RecordActionDTO
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public ?int $accountId,
        public ?int $userId,
        public string $action,
        public ActionOrigin $origin,
        public array $payload = [],
        public ?string $entityType = null,
        public ?int $entityId = null,
        public ?string $ip = null,
    ) {}
}
