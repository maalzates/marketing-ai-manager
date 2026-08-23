<?php

declare(strict_types=1);

namespace App\Modules\Admin\Domain\Contracts;

use App\Modules\Admin\Application\DTO\ApiKeyFilterDTO;
use App\Modules\Admin\Application\DTO\CreateApiKeyDTO;
use App\Modules\Admin\Infrastructure\Persistence\ApplicationApiKey;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ApplicationApiKeyRepositoryInterface
{
    /** @return LengthAwarePaginator<int, ApplicationApiKey> */
    public function findAll(ApiKeyFilterDTO $filters): LengthAwarePaginator;

    public function findById(int $id): ?ApplicationApiKey;

    /** The plaintext never crosses this boundary: the service hashes it and keeps the prefix. */
    public function create(CreateApiKeyDTO $dto, string $prefix, string $tokenHash): ApplicationApiKey;

    public function revoke(ApplicationApiKey $key): ApplicationApiKey;
}
