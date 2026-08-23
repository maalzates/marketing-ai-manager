<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Services;

use App\Modules\Admin\Application\DTO\ApiKeyFilterDTO;
use App\Modules\Admin\Application\DTO\CreateApiKeyDTO;
use App\Modules\Admin\Domain\Contracts\ApplicationApiKeyRepositoryInterface;
use App\Modules\Admin\Domain\Exceptions\ApiKeyAlreadyRevokedException;
use App\Modules\Admin\Domain\Exceptions\ApiKeyNotFoundException;
use App\Modules\Admin\Domain\Support\IssuedApiKey;
use App\Modules\Admin\Infrastructure\Persistence\ApplicationApiKey;
use App\Modules\Audit\Application\DTO\RecordActionDTO;
use App\Modules\Audit\Application\Services\ActionLogService;
use App\Modules\Audit\Domain\Enums\ActionOrigin;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

/**
 * Tokens are stored the way Sanctum stores them: a SHA-256 hash plus a readable prefix.
 * The plaintext exists inside create() and inside the creation response, and nowhere else
 * — not in the database, not in the action log, not in an exception context. Recovering a
 * token after issuance is not a missing feature; it is the property that makes a leak of
 * this table worthless (core.md §7, §10.4c).
 */
readonly class ApiKeyService
{
    private const string TOKEN_NAMESPACE = 'mk_live_';

    private const int TOKEN_RANDOM_LENGTH = 40;

    private const int VISIBLE_PREFIX_LENGTH = 16;

    private const string HASH_ALGORITHM = 'sha256';

    private const string ENTITY_TYPE = 'application_api_key';

    private const string CREATED_ACTION = 'admin.api_key.created';

    private const string REVOKED_ACTION = 'admin.api_key.revoked';

    public function __construct(
        private ApplicationApiKeyRepositoryInterface $repository,
        private ActionLogService $actionLog,
    ) {}

    /** @return LengthAwarePaginator<int, ApplicationApiKey> */
    public function findAll(ApiKeyFilterDTO $filters): LengthAwarePaginator
    {
        return $this->repository->findAll($filters);
    }

    public function create(CreateApiKeyDTO $dto): IssuedApiKey
    {
        $plainToken = self::TOKEN_NAMESPACE.Str::random(self::TOKEN_RANDOM_LENGTH);
        $key = $this->repository->create(
            $dto,
            substr($plainToken, 0, self::VISIBLE_PREFIX_LENGTH),
            hash(self::HASH_ALGORITHM, $plainToken),
        );

        $this->record($key, self::CREATED_ACTION, $dto->createdByUserId);

        return new IssuedApiKey($key, $plainToken);
    }

    public function revoke(int $id, int $actorUserId): ApplicationApiKey
    {
        $key = $this->findById($id);

        if ($key->revoked_at !== null) {
            throw ApiKeyAlreadyRevokedException::withPrefix($key->prefix);
        }

        $this->record($key, self::REVOKED_ACTION, $actorUserId);

        return $this->repository->revoke($key);
    }

    public function findById(int $id): ApplicationApiKey
    {
        return $this->repository->findById($id) ?? throw ApiKeyNotFoundException::withId($id);
    }

    private function record(ApplicationApiKey $key, string $action, int $actorUserId): void
    {
        $this->actionLog->record(new RecordActionDTO(
            $key->account_id,
            $actorUserId,
            $action,
            ActionOrigin::UI,
            // Prefix and name only. Anything closer to the token has no business being here.
            ['name' => $key->name, 'prefix' => $key->prefix, 'abilities' => $key->abilities],
            self::ENTITY_TYPE,
            $key->id,
        ));
    }
}
